<?php

namespace App\Enum;

enum CRMChannel: string
{
    use HasOptions;

    case Whatsapp = 'whatsapp';
    case Email = 'email';

    public function label(): string
    {
        return match ($this) {
            self::Whatsapp => 'WhatsApp',
            self::Email => 'Email',
        };
    }
}
