<?php

namespace App\Actions\Project;

use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;
use App\Enum\PaymentType;
use App\Enum\ProjectStatus;
use App\Enum\SessionStatus;
use App\Enum\StageStatus;
use App\Enum\StageType;
use App\Enum\TrackProgressStatus;
use App\Enum\TrackResultReviewDecision;
use App\Models\Payment;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\TrackStageProgress;
use App\Models\TrackStageSubmission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewTrackResult
{
    public function handle(TrackStageProgress $progress, TrackStageSubmission $submission, User $manager, TrackResultReviewDecision $decision, ?string $reviewNotes): TrackStageProgress
    {
        return DB::transaction(function () use ($progress, $submission, $manager, $decision, $reviewNotes): TrackStageProgress {
            $progress = TrackStageProgress::query()->lockForUpdate()->findOrFail($progress->id);
            /** @var ProjectStage $stage */
            $stage = $progress->stage()->lockForUpdate()->firstOrFail();
            /** @var Project $project */
            $project = $stage->project()->lockForUpdate()->firstOrFail();
            /** @var TrackStageSubmission $submission */
            $submission = $progress->submissions()->lockForUpdate()->findOrFail($submission->id);

            if (! $manager->isManager()) {
                throw ValidationException::withMessages(['manager' => 'Hanya Manager yang dapat meninjau hasil.']);
            }

            if ($submission->version !== $progress->current_version || $submission->version !== $progress->submissions()->max('version')) {
                throw ValidationException::withMessages(['submission' => 'Submission sudah tidak aktif untuk ditinjau.']);
            }

            if ($progress->getRawOriginal('status') !== TrackProgressStatus::InReview->value
                || $stage->getRawOriginal('status') === StageStatus::Completed->value
                || $project->getRawOriginal('status') !== ProjectStatus::InProgress->value) {
                throw ValidationException::withMessages(['progress' => 'Progress, tahap, atau Project tidak menerima review.']);
            }

            $reviewedAt = now();
            $submission->update([
                'review_notes' => $decision === TrackResultReviewDecision::RequestRevision ? $reviewNotes : null,
                'reviewed_by' => $manager->id,
                'reviewed_at' => $reviewedAt,
                'review_decision' => $decision->value,
            ]);

            $progress->update([
                'status' => $decision === TrackResultReviewDecision::Approve
                    ? TrackProgressStatus::Approved
                    : TrackProgressStatus::Revision,
                'review_notes' => $decision === TrackResultReviewDecision::RequestRevision ? $reviewNotes : null,
                'revision_count' => $progress->revision_count + ($decision === TrackResultReviewDecision::RequestRevision ? 1 : 0),
                'approved_at' => $decision === TrackResultReviewDecision::Approve ? $reviewedAt : null,
                'approved_by' => $decision === TrackResultReviewDecision::Approve ? $manager->id : null,
            ]);

            if ($decision === TrackResultReviewDecision::Approve) {
                $this->completeStageAndUnlockNext($project, $stage);
            }

            $progress->setRelation('submissions', collect([$submission]));

            return $progress;
        });
    }

    private function completeStageAndUnlockNext(Project $project, ProjectStage $stage): void
    {
        $progressRows = TrackStageProgress::query()
            ->where('project_stage_id', $stage->id)
            ->lockForUpdate()
            ->get();
        $trackIds = $project->tracks()->pluck('id');
        $expectedCount = $trackIds->count();

        if ($expectedCount === 0 || $progressRows->count() !== $expectedCount
            || $progressRows->pluck('project_track_id')->diff($trackIds)->isNotEmpty()
            || $progressRows->contains(fn (TrackStageProgress $row): bool => $row->getRawOriginal('status') !== TrackProgressStatus::Approved->value)) {
            return;
        }

        if ($stage->status === StageStatus::Completed) {
            return;
        }

        $stage->update([
            'status' => StageStatus::Completed,
            'completed_at' => now(),
        ]);

        $includedStages = array_flip($project->includedStageValues());
        $nextStage = ProjectStage::query()
            ->where('project_id', $project->id)
            ->where('sequence', '>', $stage->sequence)
            ->orderBy('sequence')
            ->lockForUpdate()
            ->get()
            ->first(fn (ProjectStage $candidate): bool => isset($includedStages[$candidate->stage->value]));

        if ($nextStage === null) {
            $this->createFinalPaymentWhenAllIncludedStagesComplete($project, $stage);

            return;
        }

        $hasIncompleteEarlierStage = ProjectStage::query()
            ->where('project_id', $project->id)
            ->where('sequence', '<', $nextStage->sequence)
            ->whereIn('stage', array_keys($includedStages))
            ->whereNot('status', StageStatus::Completed->value)
            ->exists();

        if (! $hasIncompleteEarlierStage && $nextStage->status === StageStatus::Locked) {
            $nextStage->update(['status' => StageStatus::Ready]);
        }
    }

    private function createFinalPaymentWhenAllIncludedStagesComplete(Project $project, ProjectStage $completedStage): void
    {
        $includedStages = $project->includedStageValues();
        if ($includedStages === [] || ! in_array($completedStage->stage->value, $includedStages, true)) {
            return;
        }

        $finalStageType = collect(StageType::cases())
            ->filter(fn (StageType $type): bool => in_array($type->value, $includedStages, true))
            ->last();

        if ($finalStageType === null || $completedStage->stage !== $finalStageType) {
            return;
        }

        $stages = ProjectStage::query()
            ->where('project_id', $project->id)
            ->whereIn('stage', $includedStages)
            ->lockForUpdate()
            ->get();

        if ($stages->count() !== count($includedStages)
            || $stages->contains(fn (ProjectStage $candidate): bool => $candidate->getRawOriginal('status') !== StageStatus::Completed->value)) {
            return;
        }

        $lockedProject = Project::query()->lockForUpdate()->findOrFail($project->id);
        if ($lockedProject->getRawOriginal('status') !== ProjectStatus::InProgress->value) {
            return;
        }

        $eligibleExtensionFees = (int) $lockedProject->recordingSessions()
            ->where('is_extension', true)
            ->whereIn('status', [SessionStatus::Confirmed->value, SessionStatus::Completed->value])
            ->sum('extra_fee');
        $paidAmount = (int) $lockedProject->payments()
            ->where('status', PaymentStatus::Paid->value)
            ->sum('amount');
        $amount = max(0, (int) $lockedProject->price + $eligibleExtensionFees - $paidAmount);

        $lockedProject->update([
            'status' => ProjectStatus::AwaitingFinalPayment,
            'final_payment_amount' => $amount,
        ]);

        $finalPaymentExists = $lockedProject->payments()
            ->where('type', PaymentType::Final->value)
            ->exists();

        if ($amount > 0 && ! $finalPaymentExists) {
            $configuredProvider = (string) config('payment.default_gateway', 'midtrans');
            $provider = match ($configuredProvider) {
                'cash', 'manual' => PaymentProvider::Cash,
                default => PaymentProvider::from($configuredProvider),
            };
            Payment::query()->create([
                'project_id' => $lockedProject->id,
                'type' => PaymentType::Final,
                'amount' => $amount,
                'status' => $provider === PaymentProvider::Cash ? PaymentStatus::PendingManualConfirmation : PaymentStatus::Pending,
                'provider' => $provider,
                'order_id' => sprintf('FINAL-%s-%s', $lockedProject->id, now()->format('YmdHis')),
                'expires_at' => $provider === PaymentProvider::Cash ? null : now()->addHours(config('payment.expiration_hours', 24)),
            ]);
        }
    }
}
