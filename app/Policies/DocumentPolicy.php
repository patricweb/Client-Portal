<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function view(User $user, Document $document): bool
    {
        return $user->hasPermission('manage_documents') || ($user->role->value === 'client' && $document->company_id === $user->company_id && $document->status !== 'void'
            && ($document->status !== 'draft' || $document->versions()->whereNotNull('published_at')->exists()));
    }

    public function update(User $user, Document $document): bool
    {
        return $user->isOwner();
    }
}
