<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class DocumentWorkflowService
{
    public function assertReady(Document $document): void
    {
        $version = $document->currentVersionRecord();
        abort_unless($version, 422, 'The document has no version.');
        $snapshot = $version->snapshot ?? [];
        if ($document->pack_template) {
            $profile = new ProviderProfile(['details' => $snapshot['provider'] ?? []]);
            if ($profile->missing()) {
                throw ValidationException::withMessages(['provider' => 'Complete and confirm the provider profile, then create a revised draft to capture those details.']);
            }
            if (! empty($snapshot['missing_fields'])) {
                throw ValidationException::withMessages(['fields' => 'Complete the highlighted fields in Edit details before sending: '.implode(', ', array_unique($snapshot['missing_fields']))]);
            }
            $definition = app(DocumentPackService::class)->definition($document->pack_template);
            if ($definition['parent']) {
                $parent = $document->parentDocument;
                $parentVersion = $parent?->versions()->where('version', $snapshot['parent_version'] ?? 0)->first();
                if (! $parent || $parent->id !== ($snapshot['parent_id'] ?? null) || $parent->company_id !== $document->company_id || $parent->type !== $definition['parent'] || ! $parentVersion?->signed_at) {
                    throw ValidationException::withMessages(['parent_document_id' => 'Link a signed parent agreement and save a revised draft before sending.']);
                }
            }
        }
        if (preg_match('/\{\{[^}]+\}\}/', $version->content)) {
            throw ValidationException::withMessages(['content' => 'Unresolved template variables remain in this document.']);
        }
    }

    public function version(Document $document, User $user, ?int $number = null): DocumentVersion
    {
        $query = $document->versions();
        if (! $user->isStaff()) {
            $query->where(function ($query) use ($document) {
                $query->whereNotNull('published_at');
                if (! in_array($document->status, ['draft', 'void'])) {
                    $query->orWhere('version', $document->current_version);
                }
            });
        }

        return ($number ? $query->where('version', $number) : $query)->firstOrFail();
    }
}
