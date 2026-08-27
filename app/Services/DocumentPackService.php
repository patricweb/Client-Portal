<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Document;
use App\Models\Project;
use App\Models\ProviderProfile;
use Illuminate\Support\Str;

class DocumentPackService
{
    public const TEMPLATES = [
        'project_confirmation' => ['title' => 'Project Confirmation', 'type' => 'project_confirmation', 'file' => '01_project_confirmation.md', 'prefix' => 'PC', 'parent' => null, 'parent_statuses' => []],
        'change_confirmation' => ['title' => 'Change Confirmation', 'type' => 'change_confirmation', 'file' => '02_change_confirmation.md', 'prefix' => 'CC', 'parent' => 'project_confirmation', 'parent_statuses' => ['accepted']],
        'delivery_confirmation' => ['title' => 'Delivery Confirmation', 'type' => 'delivery_confirmation', 'file' => '03_delivery_confirmation.md', 'prefix' => 'DC', 'parent' => 'project_confirmation', 'parent_statuses' => ['accepted']],
    ];

    public function definition(string $key): array
    {
        abort_unless(isset(self::TEMPLATES[$key]), 404);

        return self::TEMPLATES[$key];
    }

    public function source(string $key): string
    {
        return file_get_contents(base_path('docs/client-documents-v2/'.$this->definition($key)['file']));
    }

    /** Each occurrence has its own stable field: a due date must never overwrite a signature date. */
    public function prepare(string $key, Company $company, ?Project $project, ?Document $parent, array $commercial = [], array $saved = []): array
    {
        $profile = ProviderProfile::current()->details;
        $contact = $company->contacts()->where('is_primary', true)->first();
        $definition = $this->definition($key);
        $number = $commercial['document_number'] ?? 'Assigned when the draft is created';
        $ids = [
            'PROJECT CONFIRMATION ID' => $key === 'project_confirmation' ? $number : $parent?->document_number,
            'CHANGE CONFIRMATION ID' => $key === 'change_confirmation' ? $number : null,
            'DELIVERY CONFIRMATION ID' => $key === 'delivery_confirmation' ? $number : null,
        ];
        $defaults = [
            'CLIENT LEGAL NAME' => $company->billing_name ?: $company->name,
            'CLIENT ADDRESS' => $company->billing_address,
            'CLIENT NOTICE EMAIL' => $company->email,
            'CLIENT NAME' => $company->billing_name ?: $company->name,
            'PROJECT NAME' => $project?->name,
            'PROJECT DESCRIPTION' => $project?->description,
            'INCLUDED WORK AND DELIVERABLES' => $project?->scope,
            'NAME / EMAIL' => trim(($contact?->name ?? '').' / '.($contact?->email ?? $company->email ?? ''), ' /'),
            'NAME / TITLE / EMAIL' => trim(($contact?->name ?? '').' / '.($contact?->job_title ?? '').' / '.($contact?->email ?? $company->email ?? ''), ' /'),
            'SPECIFIC PROJECT PURPOSE AND INTENDED USERS' => $project?->description,
            'EXCLUSIONS' => $project?->exclusions,
            'FEATURES, CONTENT, MIGRATION, INTEGRATIONS, DEVICES OR SERVICES NOT INCLUDED' => $project?->exclusions,
            'DATE / ESTIMATE' => $commercial['target_date'] ?? $project?->target_completion_date?->format('Y-m-d'),
            'TARGET DATE' => $commercial['target_date'] ?? $project?->target_completion_date?->format('Y-m-d'),
            'TOTAL' => isset($commercial['price']) ? number_format((float) $commercial['price'], 2, '.', '') : null,
            'TO BE CONFIRMED' => $profile['tax_note'] ?? null,
        ];
        $automatic = [
            'PROVIDER LEGAL NAME' => $profile['legal_name'] ?? '', 'PROVIDER ADDRESS' => $profile['address'] ?? '', 'PROVIDER EMAIL' => $profile['email'] ?? '',
        ] + array_filter($ids, fn ($value) => filled($value));
        if (filled($commercial['target_date'] ?? null)) {
            $automatic['DATE / ESTIMATE'] = $commercial['target_date'];
            $automatic['DATE OR DAYS AFTER START'] = $commercial['target_date'];
            $automatic['TARGET DATE'] = $commercial['target_date'];
        }
        if (isset($commercial['price']) && $key === 'project_confirmation') {
            $automatic['AMOUNT'] = number_format((float) $commercial['price'], 2, '.', '');
            $automatic['TOTAL'] = number_format((float) $commercial['price'], 2, '.', '');
        }
        if ($key === 'change_confirmation' && $parent && isset($commercial['price'])) {
            $previous = $commercial['previous_total'] ?? app(InvoiceService::class)->agreementTotal($parent);
            $automatic['PREVIOUS TOTAL'] = number_format((float) $previous, 2, '.', '');
            $automatic['NEW TOTAL'] = number_format((float) $commercial['price'], 2, '.', '');
            $automatic['PRICE DIFFERENCE'] = number_format((float) $commercial['price'] - $previous, 2, '.', '');
        }
        $sections = [];
        $fields = [];
        $lines = [];
        $section = 'Document details';
        $counter = 0;
        foreach (explode("\n", $this->source($key)) as $line) {
            if (str_starts_with($line, '## ')) {
                $section = substr($line, 3);
            }
            // These lines are completed by the signers/approver, not fabricated by the generator.
            $executionLine = preg_match('/^(Provider:.*Signature|Client: \[LEGAL NAME\]|Client signature|Client recipient|Additional required|Required additional|Client authorized contact|Decision date|Provider confirmation|Client response|Contact \/ date)/i', $line)
                || str_starts_with($line, 'Express acceptance wording');
            $fieldOffset = 0;
            $tableCell = str_starts_with(ltrim($line), '|');
            $line = preg_replace_callback('/\[([^\]\n]+)\]/', function ($match) use (&$counter, &$fields, &$sections, &$fieldOffset, $line, $section, $executionLine, $automatic, $defaults, $saved, $key, $commercial, $tableCell) {
                $position = strpos($line, $match[0], $fieldOffset);
                $fieldOffset = $position + strlen($match[0]);
                $context = trim(preg_replace('/\[([^\]]+)\]/', '...', substr($line, 0, $position).'THIS FIELD'.substr($line, $fieldOffset)), '| ');
                $token = trim($match[1]);
                if ($token === '') {
                    return '[ ]';
                }
                $id = sprintf('field_%03d', ++$counter);
                if ($executionLine || str_contains($token, 'SIGNATURE')) {
                    $fields[$id] = ['label' => $token, 'section' => $section, 'automatic' => true, 'value' => '________________', 'execution' => true];

                    return 'PACKVALUE'.$id.'END';
                }
                $auto = array_key_exists($token, $automatic);
                $value = $auto ? $automatic[$token] : ($saved[$id] ?? $defaults[$token] ?? '');
                if (! $auto && ! array_key_exists($id, $saved) && $token === 'AMOUNT' && $key === 'project_confirmation' && isset($commercial['price'])) {
                    $value = number_format((float) $commercial['price'], 2, '.', '');
                }
                $fields[$id] = ['label' => Str::ucfirst(Str::lower($token)), 'token' => $token, 'section' => $section, 'context' => Str::limit($context, 220), 'automatic' => $auto, 'value' => (string) ($value ?? ''), 'max_length' => $tableCell ? 1000 : 8000, 'table_cell' => $tableCell];
                $sections[$section][] = $id;

                return 'PACKVALUE'.$id.'END';
            }, $line);
            $lines[] = $line;
        }
        $markdown = implode("\n", $lines);
        // Provider identity comes from the reviewed profile, never from the brand or a hard-coded company.
        $markdown = str_replace('Matei Patric', 'PACKPROVIDERNAMEEND', $markdown);
        $markdown = str_replace('Ikira is a presentation brand', 'PACKBRANDNAMEEND is a presentation brand', $markdown);
        $markdown = str_replace('Republic of Moldova', 'PACKPROVIDERCOUNTRYEND', $markdown);
        // Governing law remains the agreed Moldova clause; country replacement affects only the provider introduction.
        $markdown = str_replace('The law of the PACKPROVIDERCOUNTRYEND governs', 'The law of the Republic of Moldova governs', $markdown);
        $markdown = str_replace('<!-- page -->', 'PACKPAGEBREAKEND', $markdown);
        $html = Str::markdown($markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false, 'renderer' => ['soft_break' => "<br>\n"]]);
        $html = str_replace('<p>PACKPAGEBREAKEND</p>', '<div class="page-break"></div>', $html);
        $missing = [];
        foreach ($fields as $id => $field) {
            if (blank($field['value'])) {
                $missing[$id] = $field['label'];
            }
            $value = filled($field['value']) ? nl2br(e($field['value'])) : '<span class="unfilled">['.e($field['label']).']</span>';
            $html = str_replace('PACKVALUE'.$id.'END', $value, $html);
        }
        $html = str_replace(['PACKPROVIDERNAMEEND', 'PACKPROVIDERCOUNTRYEND', 'PACKBRANDNAMEEND'], [e($profile['legal_name'] ?? ''), e($profile['country'] ?? ''), e(($profile['brand_name'] ?? '') ?: 'The portal brand')], $html);

        return compact('definition', 'fields', 'sections', 'html', 'missing') + ['source_hash' => hash('sha256', $this->source($key))];
    }
}
