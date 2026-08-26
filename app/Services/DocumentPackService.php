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
        'msa' => ['title' => 'Master Services Agreement', 'type' => 'contract', 'file' => '01_master_services_agreement.md', 'prefix' => 'MSA', 'parent' => null],
        'proposal' => ['title' => 'Project Proposal', 'type' => 'proposal', 'file' => '02_proposal.md', 'prefix' => 'PROP', 'parent' => null],
        'sow' => ['title' => 'Statement of Work', 'type' => 'scope_of_work', 'file' => '03_statement_of_work.md', 'prefix' => 'SOW', 'parent' => 'contract'],
        'change_order' => ['title' => 'Change Order', 'type' => 'change_order', 'file' => '04_change_order.md', 'prefix' => 'CO', 'parent' => 'scope_of_work'],
        'acceptance' => ['title' => 'Delivery & Acceptance Confirmation', 'type' => 'delivery_acceptance', 'file' => '07_delivery_acceptance.md', 'prefix' => 'ACC', 'parent' => 'scope_of_work'],
        'handover' => ['title' => 'Final Handover & Rights Record', 'type' => 'project_handover', 'file' => '08_final_handover.md', 'prefix' => 'HO', 'parent' => 'scope_of_work'],
        'care' => ['title' => 'Care & Support Agreement', 'type' => 'care_support_agreement', 'file' => '09_care_support_agreement.md', 'prefix' => 'CARE', 'parent' => 'contract'],
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
        $msa = $parent?->type === 'contract' ? $parent : $parent?->parentDocument;
        $ids = [
            'MSA-ID' => $key === 'msa' ? $number : $msa?->document_number,
            'SOW-ID' => $key === 'sow' ? $number : ($parent?->type === 'scope_of_work' ? $parent->document_number : null),
            'PROPOSAL-ID' => $key === 'proposal' ? $number : null,
            'CO-ID' => $key === 'change_order' ? $number : null,
            'ACCEPTANCE-ID' => $key === 'acceptance' ? $number : null,
            'HANDOVER-ID' => $key === 'handover' ? $number : null,
            'CARE-ID' => $key === 'care' ? $number : null,
        ];
        $defaults = [
            'CLIENT LEGAL NAME' => $company->billing_name ?: $company->name,
            'CLIENT ADDRESS' => $company->billing_address,
            'CLIENT NOTICE EMAIL' => $company->email,
            'CLIENT NAME' => $company->billing_name ?: $company->name,
            'PROJECT NAME' => $project?->name,
            'NAME / EMAIL' => trim(($contact?->name ?? '').' / '.($contact?->email ?? $company->email ?? ''), ' /'),
            'NAME / TITLE / EMAIL' => trim(($contact?->name ?? '').' / '.($contact?->job_title ?? '').' / '.($contact?->email ?? $company->email ?? ''), ' /'),
            'SPECIFIC PROJECT PURPOSE AND INTENDED USERS' => $project?->description,
            'EXCLUSIONS' => $project?->exclusions,
            'FEATURES, CONTENT, MIGRATION, INTEGRATIONS, DEVICES OR SERVICES NOT INCLUDED' => $project?->exclusions,
            'DATE / ESTIMATE' => $commercial['target_date'] ?? $project?->target_completion_date?->format('Y-m-d'),
            'TOTAL' => isset($commercial['price']) ? number_format((float) $commercial['price'], 2, '.', '') : null,
            'TO BE CONFIRMED' => $profile['tax_note'] ?? null,
        ];
        $automatic = [
            'PROVIDER ADDRESS' => $profile['address'] ?? '', 'PROVIDER EMAIL' => $profile['email'] ?? '',
        ] + array_filter($ids, fn ($value) => filled($value));
        if (filled($commercial['target_date'] ?? null)) {
            $automatic['DATE / ESTIMATE'] = $commercial['target_date'];
            $automatic['DATE OR DAYS AFTER START'] = $commercial['target_date'];
        }
        if (isset($commercial['price']) && in_array($key, ['sow', 'proposal'])) {
            $automatic['AMOUNT'] = number_format((float) $commercial['price'] * ($key === 'sow' ? 0.5 : 1), 2, '.', '');
            $automatic['TOTAL'] = number_format((float) $commercial['price'], 2, '.', '');
        }
        if ($key === 'change_order' && $parent && isset($commercial['price'])) {
            $previous = $commercial['previous_total'] ?? app(InvoiceService::class)->agreementTotal($parent);
            $automatic['A'] = number_format((float) $previous, 2, '.', '');
            $automatic['A + B'] = number_format((float) $commercial['price'], 2, '.', '');
            $automatic['SIGNED CHANGE B'] = number_format((float) $commercial['price'] - $previous, 2, '.', '');
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
                if (! $auto && ! array_key_exists($id, $saved) && $token === 'AMOUNT' && in_array($key, ['proposal', 'sow']) && isset($commercial['price'])) {
                    $value = number_format((float) $commercial['price'] * ($key === 'sow' ? 0.5 : 1), 2, '.', '');
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
