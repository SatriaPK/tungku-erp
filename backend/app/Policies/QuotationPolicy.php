<?php

namespace App\Policies;

use App\Models\Quotation;
use App\Models\User;

class QuotationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isClient() || $user->isManager();
    }

    public function view(User $user, Quotation $quotation): bool
    {
        return $user->isManager()
            || ($user->isClient() && $quotation->client_id === $user->id);
    }

    public function respond(User $user, Quotation $quotation): bool
    {
        if ($user->isManager()) {
            return true;
        }

        return $user->isClient() && $quotation->client_id === $user->id;
    }
}
