<?php

namespace App\Actions\Project;

use App\Enum\ProjectStatus;
use App\Enum\StageStatus;
use App\Models\ProjectStage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmStageDeadline
{
    public function __construct(private RecordOperatorActivity $activities) {}

    public function handle(ProjectStage $stage, User $operator, CarbonImmutable $deadline): ProjectStage
    {
        return DB::transaction(function () use ($stage, $operator, $deadline): ProjectStage {
            $stage = ProjectStage::query()
                ->with('project')
                ->lockForUpdate()
                ->findOrFail($stage->id);

            $project = $stage->project()->lockForUpdate()->firstOrFail();
            $projectStatus = $project->getAttribute('status');

            if ($stage->operator_id !== $operator->id) {
                throw ValidationException::withMessages([
                    'stage' => 'Hanya Operator yang ditugaskan dapat mengonfirmasi tenggat tahap ini.',
                ]);
            }

            if (! $stage->stage->requiresDeadline() || $stage->status !== StageStatus::Ready || $stage->started_at !== null || $stage->deadline_at !== null) {
                throw ValidationException::withMessages([
                    'stage' => 'Tenggat hanya dapat dikonfirmasi satu kali pada tahap yang siap dimulai.',
                ]);
            }

            if ($projectStatus !== ProjectStatus::InProgress) {
                throw ValidationException::withMessages([
                    'stage' => 'Tahap hanya dapat dimulai selama Project masih berjalan.',
                ]);
            }

            $hasIncompleteEarlierStage = ProjectStage::query()
                ->where('project_id', $stage->project_id)
                ->where('sequence', '<', $stage->sequence)
                ->whereNot('status', StageStatus::Completed->value)
                ->exists();

            if ($hasIncompleteEarlierStage) {
                throw ValidationException::withMessages([
                    'stage' => 'Tahap ini belum boleh dimulai karena tahap sebelumnya belum selesai.',
                ]);
            }

            if ($deadline->lessThanOrEqualTo(now())) {
                throw ValidationException::withMessages([
                    'deadline_at' => 'Tenggat harus berada di masa depan.',
                ]);
            }

            $stage->update([
                'deadline_at' => $deadline->setTimezone(config('app.timezone')),
                'started_at' => now(),
                'status' => StageStatus::InProgress,
            ]);

            $this->activities->deadlineConfirmed($stage, $project, $operator);

            return $stage->refresh();
        });
    }
}
