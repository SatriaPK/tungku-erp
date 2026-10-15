<?php

namespace App\Actions\Finance;

use App\Enum\PayrollStatus;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Payroll;
use Carbon\CarbonImmutable;

class GetMonthlySummary
{
    /**
     * @return array{period: array{year: int, month: int, timezone: string}, totals: array{revenue: int, expense: int, net_profit: int}, expenses_by_category: array<int, array{category_id: int, category_name: string, amount: int}>, status: string}
     */
    public function handle(int $year, int $month): array
    {
        $timezone = config('app.timezone');
        $periodStart = CarbonImmutable::create($year, $month, 1, 0, 0, 0, $timezone);
        $periodEnd = $periodStart->addMonth();
        $payroll = Payroll::query()
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->first();

        if ($payroll?->getRawOriginal('status') === PayrollStatus::Finalized->value) {
            return [
                'period' => ['year' => $year, 'month' => $month, 'timezone' => $timezone],
                'totals' => [
                    'revenue' => $payroll->total_revenue,
                    'expense' => $payroll->total_expense,
                    'net_profit' => $payroll->net_profit,
                ],
                'expenses_by_category' => [],
                'status' => PayrollStatus::Finalized->value,
            ];
        }

        $revenue = (int) Payment::query()
            ->paid()
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $periodStart->toDateTimeString())
            ->where('paid_at', '<', $periodEnd->toDateTimeString())
            ->sum('amount');

        $expensesByCategory = Expense::query()
            ->join('expense_categories', 'expenses.expense_category_id', '=', 'expense_categories.id')
            ->where('expenses.spent_on', '>=', $periodStart->toDateString())
            ->where('expenses.spent_on', '<', $periodEnd->toDateString())
            ->groupBy('expense_categories.id', 'expense_categories.name')
            ->orderBy('expense_categories.name')
            ->selectRaw('expense_categories.id as category_id, expense_categories.name as category_name, SUM(expenses.amount) as amount')
            ->get()
            ->map(fn (Expense $expense): array => [
                'category_id' => (int) $expense->getAttribute('category_id'),
                'category_name' => (string) $expense->getAttribute('category_name'),
                'amount' => (int) $expense->getAttribute('amount'),
            ])
            ->all();

        $expense = array_sum(array_column($expensesByCategory, 'amount'));

        return [
            'period' => ['year' => $year, 'month' => $month, 'timezone' => $timezone],
            'totals' => ['revenue' => $revenue, 'expense' => $expense, 'net_profit' => $revenue - $expense],
            'expenses_by_category' => $expensesByCategory,
            'status' => $payroll === null ? PayrollStatus::Draft->value : (string) $payroll->getRawOriginal('status'),
        ];
    }
}
