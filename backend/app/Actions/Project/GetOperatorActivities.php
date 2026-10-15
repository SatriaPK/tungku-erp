<?php

namespace App\Actions\Project;

use App\Enum\OperatorActivityType;
use App\Models\OperatorActivity;
use Carbon\CarbonImmutable;

class GetOperatorActivities
{
    /**
     * @return list<array{id: int, type: string, actor: array{id: ?int, name: ?string, is_system: bool}, operator: array{id: ?int, name: ?string}, project: array{id: ?int, name: ?string}, stage: array{id: ?int, type: string, label: string}, track: array{id: ?int, title: ?string}, occurred_at: string, recorded_at: string, deadline_at: ?string, age_seconds: int, description: string}>
     */
    public function handle(CarbonImmutable $asOf): array
    {
        $timezone = config('app.timezone');
        $asOf = $asOf->setTimezone($timezone);

        return OperatorActivity::query()
            ->select('id', 'type', 'actor_id', 'actor_name', 'operator_id', 'operator_name', 'project_id', 'project_name',
                'project_stage_id', 'stage', 'project_track_id', 'track_title', 'occurred_at', 'recorded_at', 'deadline_at')
            ->where('occurred_at', '<=', $asOf->toDateTimeString())
            ->orderByDesc('occurred_at')->orderByDesc('id')->get()
            ->map(function (OperatorActivity $activity) use ($asOf, $timezone): array {
                $actor = $activity->actor_name ?? 'Operator tidak tersedia';
                $operator = $activity->operator_name ?? 'Tidak tersedia';
                $project = $activity->project_name ?? 'Project tidak tersedia';
                $track = $activity->track_title ?? 'Track tidak tersedia';
                $stage = $activity->stage->label();

                return [
                    'id' => $activity->id, 'type' => $activity->type->value,
                    'actor' => ['id' => $activity->actor_id, 'name' => $activity->actor_name,
                        'is_system' => $activity->type === OperatorActivityType::StageOverdue],
                    'operator' => ['id' => $activity->operator_id, 'name' => $activity->operator_name],
                    'project' => ['id' => $activity->project_id, 'name' => $activity->project_name],
                    'stage' => ['id' => $activity->project_stage_id, 'type' => $activity->stage->value, 'label' => $stage],
                    'track' => ['id' => $activity->project_track_id, 'title' => $activity->track_title],
                    'occurred_at' => $activity->occurred_at->setTimezone($timezone)->toIso8601String(),
                    'recorded_at' => $activity->recorded_at->setTimezone($timezone)->toIso8601String(),
                    'deadline_at' => $activity->deadline_at?->setTimezone($timezone)->toIso8601String(),
                    'age_seconds' => max(0, (int) floor($activity->occurred_at->diffInSeconds($asOf))),
                    'description' => match ($activity->type) {
                        OperatorActivityType::ResultSubmitted => "{$actor} mengirim link hasil track {$track}, dari project {$project}.",
                        OperatorActivityType::DeadlineConfirmed => "{$actor} menetapkan tenggat tahap {$stage}, pada project {$project}.",
                        OperatorActivityType::StageOverdue => "Sistem mendeteksi tahap {$stage} pada project {$project} melewati tenggat; operator saat deteksi: {$operator}.",
                    },
                ];
            })->all();
    }
}
