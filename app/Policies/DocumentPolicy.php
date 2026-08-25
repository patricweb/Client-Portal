<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function view(User $user, Document $document): bool
    {
        return $user->isOwner() || ($document->company_id === $user->company_id && $document->status !== 'draft');
    }

    public function update(User $user, Document $document): bool
    {
        return $user->isOwner();
    }
}
