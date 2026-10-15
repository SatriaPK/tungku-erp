<?php

namespace App\Actions\Project;

use App\Enum\ProjectStatus;
use App\Enum\StageStatus;
use App\Enum\TrackProgressStatus;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\ProjectTrack;
use App\Models\TrackStageProgress;
use App\Models\TrackStageSubmission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitTrackResult
{
    public function __construct(private RecordOperatorActivity $activities) {}

    public function handle(TrackStageProgress $progress, ProjectTrack $track, User $operator, string $resultUrl): TrackStageSubmission
    {
        return DB::transaction(function () use ($progress, $track, $operator, $resultUrl): TrackStageSubmission {
            $progress = TrackStageProgress::query()
                ->lockForUpdate()
                ->findOrFail($progress->id);
            /** @var ProjectStage $stage */
            $stage = $progress->stage()->lockForUpdate()->firstOrFail();
            /** @var Project $project */
            $project = $stage->project()->lockForUpdate()->firstOrFail();
            $track = ProjectTrack::query()->lockForUpdate()->findOrFail($track->id);

            if ($stage->operator_id !== $operator->id) {
                throw ValidationException::withMessages(['stage' => 'Operator tidak ditugaskan pada tahap ini.']);
            }

            if ($track->project_id !== $project->id || $progress->project_track_id !== $track->id || $progress->project_stage_id !== $stage->id) {
                throw ValidationException::withMessages(['track' => 'Track tidak termasuk dalam tahap dan Project ini.']);
            }

            if ($project->getRawOriginal('status') !== ProjectStatus::InProgress->value || ! StageStatus::from($stage->getRawOriginal('status'))->acceptsSubmissions()) {
                throw ValidationException::withMessages(['stage' => 'Project atau tahap tidak menerima submission.']);
            }

            if (! TrackProgressStatus::from($progress->getRawOriginal('status'))->awaitsOperator()) {
                throw ValidationException::withMessages(['progress' => 'Sel track/tahap belum dapat dikirim atau sudah disetujui.']);
            }

            $version = (int) $progress->submissions()->max('version') + 1;
            $submission = new TrackStageSubmission([
                'version' => $version,
                'result_url' => $resultUrl,
                'submitted_by' => $operator->id,
                'submitted_at' => now(),
            ]);
            /** @var TrackStageSubmission $submission */
            $progress->submissions()->save($submission);

            $progress->update([
                'current_version' => $version,
                'result_url' => $resultUrl,
                'submitted_at' => $submission->getAttribute('submitted_at'),
                'status' => TrackProgressStatus::InReview,
            ]);

            $this->activities->resultSubmitted($submission, $project, $stage, $track);

            return $submission;
        });
    }
}
