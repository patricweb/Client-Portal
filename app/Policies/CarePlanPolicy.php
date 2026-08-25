<?php

namespace App\Policies;

use App\Models\CarePlan;
use App\Models\User;

class CarePlanPolicy
{
    public function view(User $user, CarePlan $carePlan): bool
    {
        return $user->isOwner() || $carePlan->company_id === $user->company_id;
    }
}
