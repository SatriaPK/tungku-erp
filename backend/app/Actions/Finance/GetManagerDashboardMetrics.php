<?php

namespace App\Actions\Finance;

use App\Enum\PaymentStatus;
use App\Enum\ProjectStatus;
use App\Enum\QuotationStatus;
use App\Enum\SessionStatus;
use App\Enum\StageStatus;
use App\Enum\StageType;
use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class GetManagerDashboardMetrics
{
    /**
     * Live integer-unit totals, not payroll snapshots or net cashflow. Future-dated
     * transactions remain in their months. Balances are lifetime totals.
     * Custom sales retain quotation identity, including the snapshot fallback.
     * Names use the earliest eligible current-month project, never the catalog.
     *
     * @return array{
     * as_of: string,
     * period: array{year: int, month: int, timezone: string, today: string, month_start: string, next_month_start: string},
     * revenue: array{current: int, previous: int, delta: int, percentage: ?float, percentage_reason: ?string, direction: string},
     * expense: array{current: int, previous: int, delta: int, percentage: ?float, percentage_reason: ?string, direction: string},
     * monthly: list<array{month: int, revenue: int, expense: int}>,
     * annual_totals: array{revenue: int, expense: int},
     * awaiting_final_payment: array{amount: int, project_count: int, positive_balance_project_count: int},
     * in_progress_projects: array{count: int, track_count: int},
     * tracks_by_stage: list<array{stage: string, stage_label: string, track_count: int}>,
     * unclassified_track_count: int,
     * quotations: array{open_count: int, awaiting_studio_count: int},
     * package_sales: array{total_count: int, items: list<array{key: string, package_id: ?int, quotation_id: ?int, name: string, project_count: int, percentage: float}>},
     * expense_categories: array{total_amount: int, transaction_count: int, items: list<array{category_id: ?int, category_name: string, transaction_count: int, amount: int, percentage: float}>}
     * }
     */
    public function handle(?CarbonImmutable $asOf = null, ?int $chartYear = null): array
    {
        $timezone = config('app.timezone');
        $asOf = ($asOf ?? CarbonImmutable::now($timezone))->setTimezone($timezone);
        $chartYear = $chartYear ?? $asOf->year;
        $start = $asOf->startOfMonth();
        $revenue = $this->monthlyTotals(
            DB::table('payments')->where('status', PaymentStatus::Paid->value)->whereNotNull('paid_at'),
            'paid_at',
            $start,
        );
        $expense = $this->monthlyTotals(DB::table('expenses'), 'spent_on', $start);
        $chartStart = $start->setDate($chartYear, 1, 1)->startOfDay();
        $chartRevenue = $chartYear === $asOf->year ? $revenue : $this->monthlyTotals(
            DB::table('payments')->where('status', PaymentStatus::Paid->value)->whereNotNull('paid_at'),
            'paid_at',
            $chartStart,
            $chartYear,
        );
        $chartExpense = $chartYear === $asOf->year ? $expense : $this->monthlyTotals(DB::table('expenses'), 'spent_on', $chartStart, $chartYear);
        $monthly = [];
        for ($month = 1; $month <= 12; $month++) {
            $monthly[] = ['month' => $month, 'revenue' => (int) $chartRevenue->{'m'.$month}, 'expense' => (int) $chartExpense->{'m'.$month}];
        }
        $balances = DB::table('projects')->where('projects.status', ProjectStatus::AwaitingFinalPayment->value)->select('projects.id')
            ->selectRaw('projects.price + COALESCE((SELECT SUM(extra_fee) FROM recording_sessions WHERE recording_sessions.project_id = projects.id AND is_extension = ? AND status IN (?, ?)), 0) - COALESCE((SELECT SUM(amount) FROM payments WHERE payments.project_id = projects.id AND status = ?), 0) AS balance', [true, SessionStatus::Confirmed->value, SessionStatus::Completed->value, PaymentStatus::Paid->value]);
        $balance = DB::query()->fromSub($balances, 'balances')
            ->selectRaw('COALESCE(SUM(CASE WHEN balance > 0 THEN balance ELSE 0 END), 0) AS amount, COUNT(*) AS project_count, COALESCE(SUM(CASE WHEN balance > 0 THEN 1 ELSE 0 END), 0) AS positive_balance_project_count')->first();
        $active = Project::query()->where('status', ProjectStatus::InProgress->value)->select('id')->withCount('tracks')
            ->selectSub(DB::table('project_stages')->select('stage')->whereColumn('project_id', 'projects.id')
                ->whereIn('status', [StageStatus::Ready->value, StageStatus::InProgress->value])
                ->orderBy('sequence')->orderBy('id')->limit(1), 'current_stage');
        $activeGroups = DB::query()->fromSub($active->toBase(), 'active')
            ->select('current_stage')->selectRaw('COUNT(*) AS project_count, COALESCE(SUM(tracks_count), 0) AS track_count')
            ->groupBy('current_stage')->get();
        $tracksByStage = [];
        foreach (StageType::cases() as $stage) {
            $tracksByStage[] = ['stage' => $stage->value, 'stage_label' => $stage->label(),
                'track_count' => (int) ($activeGroups->firstWhere('current_stage', $stage->value)->track_count ?? 0)];
        }
        $quotations = DB::table('quotations')->whereIn('status', [QuotationStatus::AwaitingStudio->value, QuotationStatus::AwaitingClient->value])
            ->selectRaw('COUNT(*) AS open_count, COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS awaiting_studio_count', [QuotationStatus::AwaitingStudio->value])->first();

        return [
            'as_of' => $asOf->toIso8601String(),
            'period' => [
                'year' => $chartYear,
                'month' => $start->month,
                'timezone' => $timezone,
                'today' => $asOf->toDateString(),
                'month_start' => $start->toIso8601String(),
                'next_month_start' => $start->addMonth()->toIso8601String(),
            ],
            'revenue' => $this->comparison((int) $revenue->{'m'.$start->month}, (int) $revenue->previous),
            'expense' => $this->comparison((int) $expense->{'m'.$start->month}, (int) $expense->previous),
            'monthly' => $monthly,
            'annual_totals' => [
                'revenue' => (int) array_sum(array_column($monthly, 'revenue')),
                'expense' => (int) array_sum(array_column($monthly, 'expense')),
            ],
            'awaiting_final_payment' => ['amount' => (int) $balance->amount, 'project_count' => (int) $balance->project_count,
                'positive_balance_project_count' => (int) $balance->positive_balance_project_count],
            'in_progress_projects' => ['count' => (int) $activeGroups->sum('project_count'), 'track_count' => (int) $activeGroups->sum('track_count')],
            'tracks_by_stage' => $tracksByStage,
            'unclassified_track_count' => (int) ($activeGroups->firstWhere('current_stage', null)->track_count ?? 0),
            'quotations' => ['open_count' => (int) $quotations->open_count, 'awaiting_studio_count' => (int) $quotations->awaiting_studio_count],
            'package_sales' => $this->packageSales($start),
            'expense_categories' => $this->expenseCategories($start),
        ];
    }

    private function monthlyTotals(Builder $query, string $column, CarbonImmutable $start, ?int $chartYear = null): object
    {
        $year = $chartYear ?? $start->year;
        $periodStart = $start->setDate($year, 1, 1)->startOfDay();
        $periods = ['previous' => $start->subMonth()];
        for ($month = 1; $month <= 12; $month++) {
            $periods['m'.$month] = $periodStart->addMonths($month - 1);
        }
        foreach ($periods as $alias => $period) {
            $query->selectRaw("COALESCE(SUM(CASE WHEN {$column} >= ? AND {$column} < ? THEN amount ELSE 0 END), 0) AS {$alias}", [$period->toDateString(), $period->addMonth()->toDateString()]);
        }

        $rangeStart = $periodStart;
        $rangeEnd = $rangeStart->addYear();
        $comparisonStart = $start->startOfMonth();

        return $query->where($column, '>=', min($rangeStart, $comparisonStart->subMonth())->toDateString())
            ->where($column, '<', max($rangeEnd, $comparisonStart->addMonth())->toDateString())->first();
    }

    /** @return array{current: int, previous: int, delta: int, percentage: ?float, percentage_reason: ?string, direction: string} */
    private function comparison(int $current, int $previous): array
    {
        $delta = $current - $previous;

        return ['current' => $current, 'previous' => $previous, 'delta' => $delta,
            'percentage' => $previous > 0 ? round($delta / $previous * 100, 2) : null,
            'percentage_reason' => $previous <= 0 ? 'previous_not_positive' : null,
            'direction' => $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'unchanged')];
    }

    /** @return array{total_count: int, items: list<array{key: string, package_id: ?int, quotation_id: ?int, name: string, project_count: int, percentage: float}>} */
    private function packageSales(CarbonImmutable $start): array
    {
        $eligible = DB::table('projects')->where('status', '!=', ProjectStatus::Cancelled->value)
            ->where('created_at', '>=', $start->toDateTimeString())
            ->where('created_at', '<', $start->addMonth()->toDateTimeString())
            ->select('id', 'created_at', 'package_id', 'quotation_id', 'package_snapshot', 'package_snapshot->quotation_id as snapshot_quotation_id');
        $integerType = in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true) ? 'SIGNED' : 'INTEGER';
        $quotationId = "CAST(COALESCE(quotation_id, snapshot_quotation_id, 0) AS {$integerType})";
        $identified = DB::query()->fromSub($eligible, 'eligible_sales')
            ->select('id', 'created_at', 'package_id', 'package_snapshot')
            ->selectRaw("CASE WHEN package_id IS NULL AND {$quotationId} > 0 THEN {$quotationId} ELSE NULL END AS sale_quotation_id");
        $ranked = DB::query()->fromSub($identified, 'identified_sales')->select('package_id', 'sale_quotation_id', 'package_snapshot')
            ->selectRaw('COUNT(*) OVER (PARTITION BY package_id, sale_quotation_id) AS project_count')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY package_id, sale_quotation_id ORDER BY created_at, id) AS snapshot_rank');
        $groups = DB::query()->fromSub($ranked, 'ranked_sales')->where('snapshot_rank', 1)->get();
        $total = (int) $groups->sum('project_count');
        $items = [];
        foreach ($groups as $group) {
            $packageId = $group->package_id === null ? null : (int) $group->package_id;
            $quotationId = $group->sale_quotation_id === null ? null : (int) $group->sale_quotation_id;
            $key = $packageId !== null ? 'package:'.$packageId : ($quotationId !== null ? 'quotation:'.$quotationId : 'unavailable');
            $snapshot = json_decode($group->package_snapshot, true);
            $name = $snapshot['name'] ?? null;
            $fallback = $packageId !== null ? 'Paket tanpa snapshot' : ($quotationId !== null ? 'Penawaran Kustom #'.$quotationId : 'Paket tidak tersedia');
            $items[] = ['key' => $key, 'package_id' => $packageId, 'quotation_id' => $quotationId,
                'name' => $key !== 'unavailable' && is_string($name) && trim($name) !== '' ? $name : $fallback,
                'project_count' => (int) $group->project_count,
                'percentage' => $total > 0 ? round((int) $group->project_count / $total * 100, 2) : 0.0];
        }
        usort($items, fn (array $left, array $right): int => ($right['project_count'] <=> $left['project_count']) ?: strcmp($left['key'], $right['key']));

        return ['total_count' => $total, 'items' => $items];
    }

    /** @return array{total_amount: int, transaction_count: int, items: list<array{category_id: ?int, category_name: string, transaction_count: int, amount: int, percentage: float}>} */
    private function expenseCategories(CarbonImmutable $start): array
    {
        $groups = DB::table('expenses')->leftJoin('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->where('spent_on', '>=', $start->toDateString())->where('spent_on', '<', $start->addMonth()->toDateString())
            ->select('expenses.expense_category_id', 'expense_categories.name')
            ->selectRaw('COUNT(*) AS transaction_count, SUM(amount) AS amount')
            ->groupBy('expenses.expense_category_id', 'expense_categories.name')
            ->orderByDesc('amount')->orderBy('expenses.expense_category_id')->get();
        $total = (int) $groups->sum('amount');
        $items = [];
        foreach ($groups as $group) {
            $items[] = ['category_id' => $group->expense_category_id === null ? null : (int) $group->expense_category_id,
                'category_name' => $group->name ?? 'Kategori tidak tersedia',
                'transaction_count' => (int) $group->transaction_count, 'amount' => (int) $group->amount,
                'percentage' => $total > 0 ? round((int) $group->amount / $total * 100, 2) : 0.0];
        }

        return ['total_amount' => $total, 'transaction_count' => (int) $groups->sum('transaction_count'), 'items' => $items];
    }
}
