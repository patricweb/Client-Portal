<?php

namespace App\Policies;

use App\Models\SupportRequest;
use App\Models\User;

class SupportRequestPolicy
{
    public function view(User $user, SupportRequest $supportRequest): bool
    {
        return $user->isOwner() || $supportRequest->company_id === $user->company_id;
    }

    public function update(User $user, SupportRequest $supportRequest): bool
    {
        return $user->isOwner() || $supportRequest->company_id === $user->company_id;
    }
}
