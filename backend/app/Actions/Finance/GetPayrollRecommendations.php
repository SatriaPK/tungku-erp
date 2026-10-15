<?php

namespace App\Actions\Finance;

use App\Enum\PayrollStatus;
use App\Enum\Role;
use App\Enum\StageType;
use App\Enum\TrackProgressStatus;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\StudioSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class GetPayrollRecommendations
{
    /**
     * @return array{period: array{year: int, month: int, timezone: string}, status: string, studio_profit_percentage: string, studio_profit_amount: int, allocations: array<int, array{operator_id: int, operator_name: string, jobs_count: int, recommended_percentage: string, percentage: string|null, amount: int}>}
     */
    public function handle(int $year, int $month): array
    {
        $timezone = config('app.timezone');
        $start = CarbonImmutable::create($year, $month, 1, 0, 0, 0, $timezone);
        $end = $start->addMonth();
        $payroll = Payroll::query()
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->first();

        if ($payroll?->getRawOriginal('status') === PayrollStatus::Finalized->value) {
            return [
                'period' => ['year' => $year, 'month' => $month, 'timezone' => $timezone],
                'status' => PayrollStatus::Finalized->value,
                'studio_profit_percentage' => $this->formatPercentage((string) $payroll->getRawOriginal('studio_profit_percentage')),
                'studio_profit_amount' => (int) $payroll->getRawOriginal('studio_profit_amount'),
                'allocations' => $this->serializeItems($payroll->items()->with('operator')->orderBy('operator_id')->get()),
            ];
        }

        $jobsByOperator = User::query()
            ->join('project_stages', 'project_stages.operator_id', '=', 'users.id')
            ->join('track_stage_progress', 'track_stage_progress.project_stage_id', '=', 'project_stages.id')
            ->where('users.role', Role::Operator->value)
            ->join('projects', 'projects.id', '=', 'project_stages.project_id')
            ->where('projects.status', 'completed')
            ->whereNotNull('projects.completed_at')
            ->where('track_stage_progress.status', TrackProgressStatus::Approved->value)
            ->whereNotNull('track_stage_progress.approved_at')
            ->where('projects.completed_at', '>=', $start->toDateTimeString())
            ->where('projects.completed_at', '<', $end->toDateTimeString())
            ->whereIn('project_stages.stage', array_map(
                static fn (StageType $stage): string => $stage->value,
                StageType::cases(),
            ))
            ->groupBy('users.id', 'users.name')
            ->selectRaw('users.id as operator_id, users.name as operator_name, COUNT(track_stage_progress.id) as jobs_count')
            ->orderBy('users.id')
            ->get()
            ->map(fn (User $operator): array => [
                'operator_id' => (int) $operator->getAttribute('operator_id'),
                'operator_name' => (string) $operator->getAttribute('operator_name'),
                'jobs_count' => (int) $operator->getAttribute('jobs_count'),
            ]);
        $totalJobs = (int) $jobsByOperator->sum('jobs_count');
        $savedItems = $payroll?->items()->get()->keyBy('operator_id') ?? collect();
        $studioPercentage = $payroll === null
            ? $this->formatPercentage((string) (StudioSetting::query()->where('key', 'studio_profit_percentage')->value('value')
                ?? StudioSetting::query()->where('key', 'po_percentage')->value('value')
                ?? '50.00'))
            : $this->formatPercentage((string) $payroll->getRawOriginal('studio_profit_percentage'));

        $allocations = $jobsByOperator->map(function (array $job) use ($savedItems, $totalJobs): array {
            $saved = $savedItems->get($job['operator_id']);

            return [
                'operator_id' => $job['operator_id'],
                'operator_name' => $job['operator_name'],
                'jobs_count' => $job['jobs_count'],
                'recommended_percentage' => $this->percentageFor($job['jobs_count'], $totalJobs),
                'percentage' => $saved instanceof PayrollItem ? (string) $saved->getRawOriginal('percentage') : null,
                'amount' => 0,
            ];
        })->all();

        return [
            'period' => ['year' => $year, 'month' => $month, 'timezone' => $timezone],
            'status' => PayrollStatus::Draft->value,
            'studio_profit_percentage' => $studioPercentage,
            'studio_profit_amount' => 0,
            'allocations' => $allocations,
        ];
    }

    /**
     * @param  Collection<int, PayrollItem>  $items
     * @return array<int, array{operator_id: int, operator_name: string, jobs_count: int, recommended_percentage: string, percentage: string|null, amount: int}>
     */
    private function serializeItems(Collection $items): array
    {
        return $items->map(function (PayrollItem $item): array {
            $operator = $item->operator;

            return [
                'operator_id' => (int) $item->operator_id,
                'operator_name' => (string) $operator?->name,
                'jobs_count' => (int) $item->jobs_count,
                'recommended_percentage' => (string) $item->getRawOriginal('recommended_percentage'),
                'percentage' => (string) $item->getRawOriginal('percentage'),
                'amount' => (int) $item->getRawOriginal('amount'),
            ];
        })->all();
    }

    private function percentageFor(int $jobsCount, int $totalJobs): string
    {
        if ($totalJobs === 0) {
            return '0.00';
        }

        $hundredths = intdiv(($jobsCount * 10_000) + intdiv($totalJobs, 2), $totalJobs);

        return sprintf('%d.%02d', intdiv($hundredths, 100), $hundredths % 100);
    }

    private function formatPercentage(string $percentage): string
    {
        [$whole, $fraction] = array_pad(explode('.', $percentage, 2), 2, '');

        return sprintf('%d.%02d', (int) $whole, (int) str_pad(substr($fraction, 0, 2), 2, '0'));
    }
}
