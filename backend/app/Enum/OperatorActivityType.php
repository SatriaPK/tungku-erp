<?php

namespace App\Enum;

enum OperatorActivityType: string
{
    use HasOptions;

    case ResultSubmitted = 'result_submitted';
    case DeadlineConfirmed = 'deadline_confirmed';
    case StageOverdue = 'stage_overdue';

    public function label(): string
    {
        return match ($this) {
            self::ResultSubmitted => 'Hasil track dikirim',
            self::DeadlineConfirmed => 'Tenggat tahap ditetapkan',
            self::StageOverdue => 'Tahap melewati tenggat',
        };
    }
}
