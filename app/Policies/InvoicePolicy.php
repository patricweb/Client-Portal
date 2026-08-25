<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function view(User $user, Invoice $invoice): bool
    {
        return $user->isOwner() || ($invoice->company_id === $user->company_id && $invoice->status !== 'draft');
    }
}
