<?php

namespace App\Actions\Finance;

use App\Enum\PayrollStatus;
use App\Models\Payroll;
use App\Models\StudioSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinalizeMonthlyPayroll
{
    public function handle(int $year, int $month, User $finalizer): Payroll
    {
        return DB::transaction(function () use ($year, $month, $finalizer): Payroll {
            $payroll = Payroll::query()->createOrFirst(
                ['period_year' => $year, 'period_month' => $month],
                ['total_revenue' => 0, 'total_expense' => 0, 'net_profit' => 0, 'studio_profit_percentage' => StudioSetting::query()->where('key', 'studio_profit_percentage')->value('value') ?? StudioSetting::query()->where('key', 'po_percentage')->value('value') ?? '50.00', 'status' => PayrollStatus::Draft],
            );
            $payroll = Payroll::query()->whereKey($payroll->id)->lockForUpdate()->firstOrFail();

            if ($payroll->getRawOriginal('status') === PayrollStatus::Finalized->value) {
                return $payroll->load('items.operator');
            }

            $periodStart = CarbonImmutable::create($year, $month, 1, 0, 0, 0, config('app.timezone'));
            if ($periodStart->greaterThanOrEqualTo(CarbonImmutable::now(config('app.timezone'))->startOfMonth())) {
                throw ValidationException::withMessages([
                    'period' => 'Hanya periode bulanan yang telah berakhir yang dapat difinalisasi.',
                ]);
            }

            $summary = app(GetMonthlySummary::class)->handle($year, $month);
            $items = $payroll->items()->lockForUpdate()->get();
            $totalHundredths = $items->sum(
                fn ($item): int => $this->percentageHundredths((string) $item->getRawOriginal('percentage')),
            );
            $studioHundredths = $this->percentageHundredths((string) $payroll->getRawOriginal('studio_profit_percentage'));
            if ($totalHundredths + $studioHundredths > 10_000) {
                throw ValidationException::withMessages([
                    'allocations' => 'Total alokasi Studio dan seluruh operator tidak boleh melebihi 100.00%.',
                ]);
            }

            if ($payroll->items()->doesntExist() && $studioHundredths > 10_000) {
                throw ValidationException::withMessages(['studio_profit_percentage' => 'Persentase share Studio tidak boleh melebihi 100.00%.']);
            }

            foreach ($items as $item) {
                $amount = intdiv(max(0, $summary['totals']['net_profit']) * $this->percentageHundredths((string) $item->getRawOriginal('percentage')), 10_000);
                $item->update(['amount' => $amount]);
            }

            $studioAmount = intdiv(max(0, $summary['totals']['net_profit']) * $studioHundredths, 10_000);

            $payroll->update([
                'total_revenue' => $summary['totals']['revenue'],
                'total_expense' => $summary['totals']['expense'],
                'net_profit' => $summary['totals']['net_profit'],
                'studio_profit_amount' => $studioAmount,
                'status' => PayrollStatus::Finalized,
                'finalized_by' => $finalizer->id,
                'finalized_at' => now(),
            ]);

            return $payroll->fresh()->load('items.operator');
        });
    }

    private function percentageHundredths(string $percentage): int
    {
        [$whole, $fraction] = array_pad(explode('.', $percentage, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }
}
