<?php

namespace App\Actions\Expense;

use App\Enum\PayrollStatus;
use App\Models\Payroll;
use App\Models\StudioSetting;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class EnsureExpensePeriodsAreOpen
{
    public function handle(CarbonInterface|string|null ...$dates): void
    {
        $periods = collect($dates)
            ->filter()
            ->map(function (CarbonInterface|string $date): array {
                $period = $date instanceof CarbonInterface
                    ? $date->copy()->setTimezone(config('app.timezone'))
                    : Carbon::parse($date, config('app.timezone'));

                return ['year' => (int) $period->format('Y'), 'month' => (int) $period->format('n')];
            })
            ->unique(fn (array $period): string => $period['year'].'-'.$period['month'])
            ->values();

        if ($periods->isEmpty()) {
            return;
        }

        foreach ($periods as $period) {
            $payroll = Payroll::query()->createOrFirst(
                ['period_year' => $period['year'], 'period_month' => $period['month']],
                ['total_revenue' => 0, 'total_expense' => 0, 'net_profit' => 0, 'studio_profit_percentage' => StudioSetting::query()->where('key', 'studio_profit_percentage')->value('value') ?? StudioSetting::query()->where('key', 'po_percentage')->value('value') ?? '50.00', 'status' => PayrollStatus::Draft],
            );
            $payroll = Payroll::query()->whereKey($payroll->id)->lockForUpdate()->firstOrFail();

            if ($payroll->getRawOriginal('status') === 'finalized') {
                throw ValidationException::withMessages([
                    'spent_on' => 'Pengeluaran pada periode yang telah difinalisasi tidak dapat diubah.',
                ]);
            }
        }
    }
}
