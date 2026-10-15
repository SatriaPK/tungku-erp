<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isClient() || $user->isManager();
    }

    public function view(User $user, Payment $payment): bool
    {
        if ($user->isManager()) {
            return true;
        }

        // Client can view payment if they own the invoice
        if ($user->isClient() && $payment->invoice) {
            return $payment->invoice->client_id === $user->id;
        }

        // Or if they own the project
        if ($user->isClient() && $payment->project) {
            return $payment->project->client_id === $user->id;
        }

        return false;
    }
}
