<?php

namespace App\Actions\Finance;

use App\Enum\PayrollStatus;
use App\Models\Payroll;
use App\Models\StudioSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SavePayrollAllocations
{
    /** @param array<int, array{operator_id: int, percentage: string}> $allocations */
    public function handle(int $year, int $month, array $allocations, ?string $studioProfitPercentage = null): Payroll
    {
        return DB::transaction(function () use ($year, $month, $allocations, $studioProfitPercentage): Payroll {
            $defaultPercentage = StudioSetting::query()->where('key', 'studio_profit_percentage')->value('value')
                ?? StudioSetting::query()->where('key', 'po_percentage')->value('value')
                ?? '50.00';
            $payroll = Payroll::query()->createOrFirst(
                ['period_year' => $year, 'period_month' => $month],
                ['total_revenue' => 0, 'total_expense' => 0, 'net_profit' => 0, 'studio_profit_percentage' => $defaultPercentage, 'status' => PayrollStatus::Draft],
            );
            $payroll = Payroll::query()->whereKey($payroll->id)->lockForUpdate()->firstOrFail();

            if ($payroll->getRawOriginal('status') !== PayrollStatus::Draft->value) {
                throw ValidationException::withMessages(['period' => 'Periode payroll yang telah difinalisasi tidak dapat diubah.']);
            }

            $totalHundredths = array_sum(array_map(
                fn (array $allocation): int => $this->percentageHundredths($allocation['percentage']),
                $allocations,
            ));

            $studioPercentage = $studioProfitPercentage === null
                ? $this->percentageHundredths((string) $payroll->getRawOriginal('studio_profit_percentage'))
                : $this->percentageHundredths($studioProfitPercentage);

            if ($totalHundredths + $studioPercentage > 10_000) {
                throw ValidationException::withMessages(['allocations' => 'Total alokasi Studio dan seluruh operator tidak boleh melebihi 100.00%.']);
            }

            $operatorIds = array_column($allocations, 'operator_id');
            if (count(array_unique($operatorIds)) !== count($operatorIds)) {
                throw ValidationException::withMessages(['allocations' => 'Setiap operator hanya boleh muncul satu kali.']);
            }

            $operators = User::query()
                ->operators()
                ->whereIn('id', $operatorIds)
                ->get()
                ->keyBy('id');

            if ($operators->count() !== count($operatorIds)) {
                throw ValidationException::withMessages(['allocations' => 'Semua operator harus merupakan akun Operator yang valid.']);
            }

            $recommendations = app(GetPayrollRecommendations::class)->handle($year, $month)['allocations'];
            $recommendedByOperator = collect($recommendations)->keyBy('operator_id');

            $eligibleOperatorIds = $recommendedByOperator->keys()->map(fn ($id): int => (int) $id)->all();
            sort($operatorIds);
            sort($eligibleOperatorIds);

            if ($operatorIds !== $eligibleOperatorIds) {
                throw ValidationException::withMessages(['allocations' => 'Daftar alokasi harus mencakup tepat seluruh operator yang memiliki pekerjaan disetujui pada periode tersebut.']);
            }

            $payroll->items()->delete();
            if ($studioProfitPercentage !== null) {
                [$whole, $fraction] = array_pad(explode('.', $studioProfitPercentage, 2), 2, '');
                $payroll->update(['studio_profit_percentage' => sprintf('%d.%02d', (int) $whole, (int) str_pad($fraction, 2, '0'))]);
            }

            foreach ($allocations as $allocation) {
                $recommendation = $recommendedByOperator->get($allocation['operator_id']);
                $payroll->items()->create([
                    'operator_id' => $allocation['operator_id'],
                    'jobs_count' => $recommendation['jobs_count'],
                    'recommended_percentage' => $recommendation['recommended_percentage'],
                    'percentage' => $allocation['percentage'],
                    'amount' => 0,
                ]);
            }

            return $payroll->load('items.operator');
        });
    }

    private function percentageHundredths(string $percentage): int
    {
        [$whole, $fraction] = array_pad(explode('.', $percentage, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }
}
