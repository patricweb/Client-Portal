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
            if ($profile->missing(business: true)) {
                throw ValidationException::withMessages(['provider' => 'Complete and confirm the provider legal identity, contracting status and registration identifier, then create a revised draft.']);
            }
            if (! empty($snapshot['missing_fields'])) {
                throw ValidationException::withMessages(['fields' => 'Complete the highlighted fields in Edit details before sending: '.implode(', ', array_unique($snapshot['missing_fields']))]);
            }
            $definition = app(DocumentPackService::class)->definition($document->pack_template);
            if ($definition['parent']) {
                $parent = $document->parentDocument;
                $parentVersion = $parent?->versions()->where('version', $snapshot['parent_version'] ?? 0)->first();
                $validParentStatus = in_array($parent?->status, $definition['parent_statuses'] ?? ['signed'], true);
                $parentFinalized = $parentVersion && ($parentVersion->signed_at || $parentVersion->locked_at);
                if (! $parent || $parent->id !== ($snapshot['parent_id'] ?? null) || $parent->company_id !== $document->company_id || $parent->project_id !== $document->project_id || $parent->type !== $definition['parent'] || ! $validParentStatus || ! $parentFinalized) {
                    throw ValidationException::withMessages(['parent_document_id' => 'Link the accepted Project Services Agreement and save a revised draft before sending.']);
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
