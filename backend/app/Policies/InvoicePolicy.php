<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isClient() || $user->isManager();
    }

    public function view(User $user, Invoice $invoice): bool
    {
        if ($user->isManager()) {
            return true;
        }

        return $user->isClient() && $invoice->client_id === $user->id;
    }
}
