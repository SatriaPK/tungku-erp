<?php

namespace App\Enum;

enum InvoiceStatus: string
{
    use HasOptions, HasTransitions;

    case Draft = 'draft';
    case Issued = 'issued';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Issued => 'Terbit',
            self::Paid => 'Lunas',
            self::Cancelled => 'Dibatalkan',
            self::Refunded => 'Dikembalikan',
        };
    }

    /**
     * @return array<int, self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::Draft => [self::Issued, self::Cancelled],
            self::Issued => [self::Paid, self::Cancelled],
            self::Paid => [self::Refunded],
            self::Cancelled, self::Refunded => [],
        };
    }

    public function isPaid(): bool
    {
        return $this === self::Paid;
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Draft, self::Issued, self::Paid], true);
    }
}
