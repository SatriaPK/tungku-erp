<?php

namespace App\Actions\Finance;

use App\Enum\PayrollStatus;
use App\Models\Payroll;
use App\Models\StudioSetting;
use Illuminate\Support\Facades\DB;
use LogicException;

class LockPayrollPeriod
{
    public function handle(int $year, int $month): Payroll
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Payroll period locks require a transaction.');
        }

        $period = ['period_year' => $year, 'period_month' => $month];
        $payroll = Payroll::query()->where($period)->lockForUpdate()->first();
        if ($payroll !== null) {
            return $payroll;
        }

        $payroll = Payroll::query()->createOrFirst($period, [
            'total_revenue' => 0,
            'total_expense' => 0,
            'net_profit' => 0,
            'studio_profit_percentage' => StudioSetting::query()->where('key', 'studio_profit_percentage')->value('value')
                ?? StudioSetting::query()->where('key', 'po_percentage')->value('value') ?? '50.00',
            'status' => PayrollStatus::Draft,
        ]);

        return Payroll::query()->whereKey($payroll->id)->lockForUpdate()->firstOrFail();
    }
}
