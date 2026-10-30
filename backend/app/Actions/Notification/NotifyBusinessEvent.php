<?php

namespace App\Actions\Notification;

use App\Models\Payment;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\Quotation;
use App\Models\RecordingSession;
use App\Models\TrackStageSubmission;
use App\Models\User;
use App\Notifications\BusinessEventNotification;
use App\Notifications\PaymentCreatedNotification;
use Illuminate\Database\Eloquent\Builder;

class NotifyBusinessEvent
{
    public function __construct(private RecordNotification $recorder) {}

    public function stage(ProjectStage $stage, string $type, ?TrackStageSubmission $submission = null): void
    {
        $metadata = ['project_id' => $stage->project_id, 'stage_id' => $stage->id, 'status' => $stage->status->value];
        if ($stage->operator_id !== null) {
            $metadata['operator_id'] = $stage->operator_id;
        }
        if ($submission !== null) {
            $metadata['submission_id'] = $submission->id;
            $metadata['version'] = $submission->version;
            $progress = $submission->progress()->firstOrFail();
            $metadata['track_id'] = $progress->project_track_id;
            $metadata['status'] = $progress->status->value;
        }
        $key = $submission === null ? 'stage:'.$stage->id.':'.($type === 'stage_assigned' ? 'assigned' : 'ready')
            : 'submission:'.$submission->id.':'.($type === 'result_submitted' ? 'submitted' : 'revision');
        $this->send($key, $type, $metadata, $stage->operator_id === null ? [] : [$stage->operator_id]);
    }

    public function quotation(Quotation $quotation, string $type): void
    {
        $offer = $quotation->latestOffer()->firstOrFail();
        $key = $type === 'quotation_countered' ? 'offer:'.$offer->id.':countered' : 'quotation:'.$quotation->id.':'.substr($type, strlen('quotation_'));
        $this->send($key, $type, ['quotation_id' => $quotation->id, 'offer_id' => $offer->id,
            'status' => $quotation->status->value, 'price' => $quotation->agreed_price ?? $offer->price], [$quotation->client_id]);
    }

    public function project(Project $project, string $type): void
    {
        $this->send('project:'.$project->id.':'.substr($type, strlen('project_')), $type,
            ['project_id' => $project->id, 'code' => $project->code, 'status' => $project->status->value],
            $type === 'project_awaiting_assignment' ? [] : [$project->client_id]);
    }

    public function extension(RecordingSession $session, int $hours, int $hourlyRate): void
    {
        $this->send('session:'.$session->id.':extended', 'recording_extended', [
            'session_id' => $session->id, 'project_id' => $session->project_id,
            'stage_id' => $session->project_stage_id, 'status' => $session->status->value,
            'start_at' => $session->start_at->toIso8601String(), 'end_at' => $session->end_at->toIso8601String(),
            'billable_hours' => $hours, 'hourly_rate' => $hourlyRate, 'extra_fee' => $session->extra_fee,
        ], [$session->client_id], false);
    }

    public function paymentCreated(Payment $payment): void
    {
        $client = $payment->invoice->client ?? $payment->project?->client;
        if ($client !== null) {
            $this->recorder->handle($client, new PaymentCreatedNotification($payment));
        }
    }

    /**
     * @param  array<string, int|string>  $metadata
     * @param  list<int>  $recipientIds
     */
    private function send(string $key, string $type, array $metadata, array $recipientIds, bool $includeManagers = true): void
    {
        $notification = new BusinessEventNotification($key, $type, $metadata);
        User::query()->where('is_active', true)->where(function (Builder $query) use ($recipientIds, $includeManagers): void {
            $query->whereIn('id', $recipientIds);
            if ($includeManagers) {
                $query->orWhere('role', 'manager');
            }
        })->eachById(fn (User $recipient) => $this->recorder->handle($recipient, $notification), 100);
    }
}
