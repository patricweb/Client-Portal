<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Project;
use App\Models\ProviderProfile;
use Illuminate\Support\Str;

class DocumentService
{
    public function render(string $content, Company $company, ?Project $project): string
    {
        $primary = $company->contacts()->where('is_primary', true)->first();
        $values = [
            '{{company.name}}' => $company->name,
            '{{company.billing_name}}' => $company->billing_name ?: $company->name,
            '{{company.email}}' => $company->email ?? '',
            '{{company.billing_address}}' => $company->billing_address ?? '',
            '{{contact.name}}' => $primary?->name ?? '',
            '{{contact.email}}' => $primary?->email ?? '',
            '{{project.name}}' => $project?->name ?? '',
            '{{project.description}}' => $project?->description ?? '',
            '{{project.scope}}' => $project?->scope ?? '',
            '{{project.exclusions}}' => $project?->exclusions ?? '',
            '{{project.price}}' => $project ? $project->currency.' '.number_format((float) $project->price, 2) : '',
            '{{project.target_date}}' => $project?->target_completion_date?->format('F j, Y') ?? '',
            '{{today}}' => now()->format('F j, Y'),
        ];

        return Str::replace(array_keys($values), array_map(e(...), array_values($values)), $content);
    }

    public function snapshot(Company $company, ?Project $project): array
    {
        return [
            'provider' => ProviderProfile::current()->details,
            'company' => $company->only(['id', 'name', 'billing_name', 'jurisdiction', 'email', 'billing_address', 'currency']),
            'primary_contact' => $company->contacts()->where('is_primary', true)->first()?->only(['id', 'user_id', 'name', 'email', 'job_title']),
            'project' => $project?->only(['id', 'name', 'type', 'description', 'scope', 'exclusions', 'price', 'currency', 'target_completion_date']),
            'generated_at' => now()->toIso8601String(),
        ];
    }
}
