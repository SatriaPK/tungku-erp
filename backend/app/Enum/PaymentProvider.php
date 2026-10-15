<?php

namespace App\Enum;

enum PaymentProvider: string
{
    use HasOptions;

    case Midtrans = 'midtrans';
    case Xendit = 'xendit';
    case Cash = 'cash';

    public function label(): string
    {
        return match ($this) {
            self::Midtrans => 'Midtrans',
            self::Xendit => 'Xendit',
            self::Cash => 'Tunai',
        };
    }

    /**
     * Apakah provider ini memerlukan integrasi payment gateway?
     * Cash tidak memerlukan gateway karena dikonfirmasi manual oleh Manager.
     */
    public function requiresGateway(): bool
    {
        return match ($this) {
            self::Midtrans, self::Xendit => true,
            self::Cash => false,
        };
    }
}
