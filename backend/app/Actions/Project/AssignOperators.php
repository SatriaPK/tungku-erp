<?php

namespace App\Actions\Project;

use App\Enum\ProjectStatus;
use App\Enum\StageStatus;
use App\Enum\StageType;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\ProjectTrack;
use App\Models\TrackStageProgress;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignOperators
{
    /**
     * Assign one active Operator to every stage included in the Project snapshot.
     *
     * @param  array<string, int|string>  $assignments  Stage value => Operator ID.
     * @return array<int, ProjectStage>
     */
    public function handle(Project $project, array $assignments): array
    {
        return DB::transaction(function () use ($project, $assignments): array {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);

            if ($project->getRawOriginal('status') !== ProjectStatus::AwaitingAssignment->value) {
                throw ValidationException::withMessages([
                    'project' => 'Operator hanya dapat ditugaskan ke Project yang menunggu penugasan.',
                ]);
            }

            $includedStages = $this->includedStages($project);
            $submittedStages = array_keys($assignments);

            if ($includedStages === [] || array_diff($includedStages, $submittedStages) !== [] || array_diff($submittedStages, $includedStages) !== []) {
                throw ValidationException::withMessages([
                    'assignments' => 'Penugasan harus mencakup tepat semua tahap aktif pada Project.',
                ]);
            }

            $stages = ProjectStage::query()
                ->where('project_id', $project->id)
                ->orderBy('sequence')
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (ProjectStage $stage): string => $stage->stage->value);

            if ($stages->count() !== count($includedStages) || $stages->keys()->diff($includedStages)->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'project' => 'Tahap Project tidak sesuai dengan tahap aktif pada paketnya.',
                ]);
            }

            foreach ($stages as $stage) {
                if ($stage->status !== StageStatus::Locked || $stage->started_at !== null || $stage->completed_at !== null) {
                    throw ValidationException::withMessages([
                        'project' => 'Penugasan hanya dapat dilakukan sebelum tahap mulai dikerjakan.',
                    ]);
                }
            }

            $operatorIds = array_values($assignments);

            if (count(array_unique($operatorIds)) !== count($operatorIds)) {
                throw ValidationException::withMessages([
                    'assignments' => 'Setiap tahap harus ditugaskan kepada Operator yang berbeda.',
                ]);
            }

            $operators = User::query()
                ->operators()
                ->active()
                ->whereIn('id', $operatorIds)
                ->get()
                ->keyBy('id');

            if ($operators->count() !== count($operatorIds)) {
                throw ValidationException::withMessages([
                    'assignments' => 'Semua ID harus merupakan akun Operator aktif yang berbeda.',
                ]);
            }

            $now = now();

            foreach ($includedStages as $index => $stageValue) {
                /** @var ProjectStage $stage */
                $stage = $stages->get($stageValue);
                $operatorId = (int) $assignments[$stageValue];

                $stage->update([
                    'operator_id' => $operatorId,
                    'assigned_at' => $now,
                    'status' => $index === 0 ? StageStatus::Ready : StageStatus::Locked,
                ]);

                foreach ($project->tracks()->orderBy('id')->get() as $track) {
                    /** @var ProjectTrack $track */
                    TrackStageProgress::query()->firstOrCreate([
                        'project_stage_id' => $stage->id,
                        'project_track_id' => $track->id,
                    ]);
                }
            }

            $project->update(['status' => ProjectStatus::InProgress]);

            /** @var array<int, ProjectStage> $assignedStages */
            $assignedStages = $project->stages()->with('operator')->get()->all();

            return $assignedStages;
        });
    }

    /**
     * @return array<int, string>
     */
    private function includedStages(Project $project): array
    {
        /** @var array<string, mixed> $snapshot */
        $snapshot = $project->getAttribute('package_snapshot') ?? [];

        if (isset($snapshot['stages']) && is_array($snapshot['stages'])) {
            return array_values(array_unique(array_map(
                static fn (StageType|string $stage): string => $stage instanceof StageType ? $stage->value : $stage,
                $snapshot['stages'],
            )));
        }

        /** @var array<int, string> $includedStages */
        $includedStages = array_map(static fn (StageType $stage): string => $stage->value, array_values(array_filter(
            StageType::cases(),
            static fn (StageType $stage): bool => (bool) ($snapshot[$stage->includeColumn()] ?? false),
        )));

        return $includedStages;
    }
}
