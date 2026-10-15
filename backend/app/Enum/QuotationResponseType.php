<?php

namespace App\Enum;

enum QuotationResponseType: string
{
    case Accept = 'accept';
    case Decline = 'decline';
    case Counter = 'counter';

    public function label(): string
    {
        return match ($this) {
            self::Accept => 'Accept',
            self::Decline => 'Decline',
            self::Counter => 'Counter',
        };
    }
}
