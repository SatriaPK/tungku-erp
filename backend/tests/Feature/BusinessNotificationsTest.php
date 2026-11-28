<?php

use App\Actions\Booking\HoldSlot;
use App\Actions\Booking\RequestRecordingExtension;
use App\Actions\Invoice\IssueInvoice;
use App\Actions\Notification\NotifyBusinessEvent;
use App\Actions\Payment\ConfirmManualPayment;
use App\Actions\Payment\ConfirmPayment;
use App\Actions\Payment\InitiatePayment;
use App\Actions\Project\AssignOperators;
use App\Actions\Project\ReviewTrackResult;
use App\Actions\Project\SubmitTrackResult;
use App\Actions\Quotation\RespondToQuotation;
use App\Actions\Quotation\SubmitQuotation;
use App\Enum\TrackResultReviewDecision;
use App\Jobs\HandlePaymentWebhook;
use App\Models\BasePrice;
use App\Models\Invoice;
use App\Models\NotificationDelivery;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\RecordingSession;
use App\Models\Room;
use App\Models\TrackStageProgress;
use App\Models\User;
use App\Models\WebhookLog;
use App\Notifications\BusinessEventNotification;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Database\Seeders\StudioSettingSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->freezeTime();
    config(['queue.default' => 'database']);
});

function businessRecipients(string $type): array
{
    return NotificationDelivery::query()->where('payload->type', $type)->where('channel', 'database')
        ->orderBy('recipient_id')->pluck('recipient_id')->all();
}

test('cash and gateway PO confirmations apply settlement before notifying active managers', function (string $provider) {
    $client = User::factory()->client()->create();
    $manager = User::factory()->manager()->create();
    User::factory()->manager()->inactive()->create();
    $project = Project::factory()->for($client, 'client')->create(['status' => 'pending_payment', 'po_amount' => 100000]);
    $invoice = Invoice::factory()->for($client, 'client')->create(['project_id' => $project->id, 'status' => 'issued', 'total' => 100000]);
    $payment = Payment::factory()->create(['project_id' => $project->id, 'invoice_id' => $invoice->id, 'provider' => $provider, 'amount' => 100000]);
    $session = RecordingSession::factory()->held()->create(['client_id' => $client->id, 'project_id' => $project->id, 'payment_id' => $payment->id]);
    if ($provider === 'cash') {
        app(ConfirmManualPayment::class)->execute($payment, 'Cash received at studio');
    } else {
        app(ConfirmPayment::class)->execute($payment);
    }
    expect($project->fresh()->status->value)->toBe('awaiting_assignment');
    expect($invoice->fresh()->status->value)->toBe('paid');
    expect($session->fresh()->status->value)->toBe('confirmed');
    expect(businessRecipients('project_awaiting_assignment'))->toBe([$manager->id]);
    expect(businessRecipients('payment_confirmed'))->toBe([$client->id]);
    expect(NotificationDelivery::query()->where('channel', 'mail')->count())->toBe(2);
})->with(['cash', 'xendit']);

test('cancelled or insufficiently paid projects do not announce readiness', function (string $status, int $amount) {
    User::factory()->manager()->create();
    $project = Project::factory()->create(['status' => $status, 'po_amount' => 100000]);
    $payment = Payment::factory()->cash()->create(['project_id' => $project->id, 'amount' => $amount]);
    app(ConfirmManualPayment::class)->execute($payment, 'Cash received');
    expect($project->fresh()->status->value)->toBe($status);
    expect(businessRecipients('project_awaiting_assignment'))->toBe([]);
})->with([['cancelled', 100000], ['pending_payment', 50000]]);

test('production hooks notify assigned operators and active managers without exposing result links', function () {
    $manager = User::factory()->manager()->create();
    $operator = User::factory()->operator()->create();
    $nextOperator = User::factory()->operator()->create();
    User::factory()->manager()->inactive()->create();
    User::factory()->operator()->create();
    $project = Project::factory()->create(['status' => 'awaiting_assignment', 'package_snapshot' => ['stages' => ['editing', 'mixing']], 'price' => 100000, 'po_amount' => 10000]);
    $stage = $project->stages()->create(['stage' => 'editing', 'sequence' => 1]);
    $nextStage = $project->stages()->create(['stage' => 'mixing', 'sequence' => 2]);
    $track = $project->tracks()->create(['title' => 'Track', 'position' => 1]);
    app(AssignOperators::class)->handle($project, ['editing' => $operator->id, 'mixing' => $nextOperator->id]);
    expect(businessRecipients('stage_assigned'))->toBe([$manager->id, $manager->id, $operator->id, $nextOperator->id]);
    $progress = TrackStageProgress::query()->where('project_stage_id', $stage->id)->sole();
    $submission = app(SubmitTrackResult::class)->handle($progress, $track, $operator, 'https://private.example/result');
    expect(businessRecipients('result_submitted'))->toBe([$manager->id, $operator->id]);
    app(ReviewTrackResult::class)->handle($progress, $submission, $manager, TrackResultReviewDecision::RequestRevision, 'Private review note');
    expect(businessRecipients('revision_requested'))->toBe([$manager->id, $operator->id]);
    expect(NotificationDelivery::query()->where('payload->type', 'revision_requested')->firstOrFail()->payload['metadata']['status'])->toBe('revision');
    $revision = app(SubmitTrackResult::class)->handle($progress, $track, $operator, 'https://private.example/revision');
    app(ReviewTrackResult::class)->handle($progress, $revision, $manager, TrackResultReviewDecision::Approve, null);
    expect(businessRecipients('stage_ready'))->toBe([$manager->id, $nextOperator->id]);
    $nextProgress = TrackStageProgress::query()->where('project_stage_id', $nextStage->id)->sole();
    $final = app(SubmitTrackResult::class)->handle($nextProgress, $track, $nextOperator, 'https://private.example/final');
    app(ReviewTrackResult::class)->handle($nextProgress, $final, $manager, TrackResultReviewDecision::Approve, null);
    expect(businessRecipients('payment_created'))->toBe([$project->client_id]);
    expect(NotificationDelivery::query()->get()->toJson())->not->toContain('private.example', 'Private review note');
    expect(Activity::query()->where('event', 'submission.created')->count())->toBe(3);
    expect(Activity::query()->where('event', 'submission.reviewed')->count())->toBe(3);
    expect(Activity::query()->where('event', 'stage.completed')->count())->toBe(2);
    expect(Activity::query()->where('event', 'project.awaiting_final_payment')->sole()->properties['actor_id'])->toBe($manager->id);
    expect(Activity::all()->toJson())->not->toContain('private.example', 'Private review note');
    $count = NotificationDelivery::query()->count();
    expect(fn () => app(ReviewTrackResult::class)->handle($nextProgress, $final, $manager, TrackResultReviewDecision::Approve, null))
        ->toThrow(ValidationException::class);
    expect(NotificationDelivery::query()->count())->toBe($count);
});

test('quotation submit counter and terminal responses notify owner and managers with safe prices', function (string $response) {
    $client = User::factory()->client()->create();
    $manager = User::factory()->manager()->create();
    User::factory()->manager()->inactive()->create();
    BasePrice::query()->create(['stage' => 'recording', 'unit' => 'hour', 'price' => 150000]);
    $quotation = app(SubmitQuotation::class)->handle($client, [
        'name' => 'Custom', 'track_count' => 1, 'recording_hours' => 1, 'include_recording' => true,
        'include_editing' => false, 'include_mixing' => false, 'include_mastering' => false,
        'offer_price' => 100000, 'notes' => 'Private quotation notes',
    ]);
    expect(businessRecipients('quotation_submitted'))->toBe([$client->id, $manager->id]);
    $quotation = app(RespondToQuotation::class)->handle($quotation, $manager, [
        'latest_offer_id' => $quotation->latestOffer->id, 'response_type' => 'counter', 'price' => 120000,
    ]);
    expect(businessRecipients('quotation_countered'))->toBe([$client->id, $manager->id]);
    app(RespondToQuotation::class)->handle($quotation, $client, ['latest_offer_id' => $quotation->latestOffer->id, 'response_type' => $response]);
    expect(businessRecipients($response === 'accept' ? 'quotation_accepted' : 'quotation_declined'))->toBe([$client->id, $manager->id]);
    expect(NotificationDelivery::query()->get()->toJson())->not->toContain('Private quotation notes');
    expect(Activity::query()->where('event', 'quotation.offer_created')->count())->toBe(2);
    expect(Activity::query()->where('event', 'quotation.'.$response)->sole()->properties['actor_id'])->toBe($client->id);
    expect(Activity::all()->toJson())->not->toContain('Private quotation notes');
})->with(['accept', 'decline']);

test('recording extension notifies only the client with immutable price and schedule details', function () {
    $this->seed(StudioSettingSeeder::class);
    $client = User::factory()->client()->create();
    $operator = User::factory()->operator()->create();
    User::factory()->manager()->create();
    $project = Project::factory()->for($client, 'client')->create(['status' => 'in_progress']);
    $stage = $project->stages()->create(['stage' => 'recording', 'sequence' => 1, 'status' => 'in_progress', 'operator_id' => $operator->id]);
    $room = Room::factory()->create();
    $price = BasePrice::query()->create(['stage' => 'recording', 'unit' => 'hour', 'price' => 150000]);
    $start = CarbonImmutable::tomorrow()->setTime(10, 0);
    $session = app(RequestRecordingExtension::class)->handle($stage, $operator, $room->id, $start, $start->addMinutes(61));
    expect($session->status->value)->toBe('confirmed');
    expect(businessRecipients('recording_extended'))->toBe([$client->id]);
    $price->update(['price' => 999]);
    $delivery = NotificationDelivery::query()->where('channel', 'mail')->sole();
    expect($delivery->payload['metadata'])->toMatchArray(['billable_hours' => 2, 'hourly_rate' => 150000, 'extra_fee' => 300000, 'start_at' => $start->toIso8601String()]);
    $notice = new BusinessEventNotification($delivery->event_key, 'recording_extended', $delivery->payload['metadata']);
    expect($notice->toMail($client)->introLines)->toContain('Biaya tambahan: Rp 300.000');
    expect(Activity::query()->where('event', 'booking.extended')->sole()->properties['attributes']['extra_fee'])->toBe(300000);
});

test('quotation booking announces project creation separately from PO readiness', function () {
    $this->seed(StudioSettingSeeder::class);
    $client = User::factory()->client()->create();
    $manager = User::factory()->manager()->create();
    $room = Room::factory()->create();
    $quotation = Quotation::query()->create(['client_id' => $client->id, 'name' => 'Custom', 'status' => 'accepted', 'agreed_price' => 100000,
        'track_count' => 1, 'recording_hours' => 1, 'include_recording' => true]);
    $session = app(HoldSlot::class)->handle($room->id, $client->id, Carbon::tomorrow()->setTime(10, 0), Carbon::tomorrow()->setTime(11, 0), quotationId: $quotation->id);
    expect(businessRecipients('project_created'))->toBe([$client->id, $manager->id]);
    expect(businessRecipients('project_awaiting_assignment'))->toBe([]);
    $invoice = Invoice::query()->where('project_id', $session->project_id)->sole();
    app(IssueInvoice::class)->execute($invoice);
    $payment = app(InitiatePayment::class)->execute($invoice->fresh(), 'cash');
    app(ConfirmManualPayment::class)->execute($payment, 'Cash received');
    expect($session->fresh()->status->value)->toBe('confirmed');
    expect($session->fresh()->payment_id)->toBe($payment->id);
    expect(Activity::query()->where('event', 'booking.held')->sole()->properties['actor_id'])->toBe($client->id);
    expect(Activity::query()->where('event', 'invoice.created')->sole()->properties['attributes']['total'])->toBe(100000);
    expect(Activity::query()->where('event', 'invoice.issued')->count())->toBe(1);
    expect(Activity::query()->where('event', 'invoice.paid')->count())->toBe(1);
    expect(businessRecipients('project_awaiting_assignment'))->toBe([$manager->id]);

});

test('invoice payment initiation records a single payment event and outer rollback removes settlement notifications', function () {
    $client = User::factory()->client()->create();
    User::factory()->manager()->create();
    $project = Project::factory()->for($client, 'client')->create(['status' => 'pending_payment', 'po_amount' => 100000]);
    $invoice = Invoice::factory()->for($client, 'client')->create(['project_id' => $project->id, 'status' => 'issued', 'total' => 100000]);
    $payment = app(InitiatePayment::class)->execute($invoice, 'cash');
    expect(businessRecipients('payment_created'))->toBe([$client->id]);
    DB::beginTransaction();
    app(ConfirmManualPayment::class)->execute($payment, 'Cash received');
    DB::rollBack();
    expect(businessRecipients('project_awaiting_assignment'))->toBe([]);
    expect(businessRecipients('payment_confirmed'))->toBe([]);
    expect($project->fresh()->status->value)->toBe('pending_payment');
});

test('business events use real inbox delivery and safe transactional mail', function () {
    config(['queue.default' => 'sync']);
    $client = User::factory()->client()->create();
    $manager = User::factory()->manager()->create();
    $project = Project::factory()->for($client, 'client')->create(['status' => 'pending_payment']);
    app(NotifyBusinessEvent::class)->project($project, 'project_created');
    expect($client->notifications()->sole()->data)->toMatchArray(['type' => 'project_created', 'schema_version' => 1]);
    expect($manager->notifications()->count())->toBe(1);
    expect(NotificationDelivery::query()->where('status', 'delivered')->count())->toBe(4);
    app(NotifyBusinessEvent::class)->project($project, 'project_created');
    expect(NotificationDelivery::query()->count())->toBe(4);
});

test('duplicate provider callbacks do not duplicate payment or project readiness events', function () {
    $manager = User::factory()->manager()->create();
    $project = Project::factory()->create(['status' => 'pending_payment', 'po_amount' => 100000]);
    $payment = Payment::factory()->create(['project_id' => $project->id, 'amount' => 100000]);
    foreach ([1, 2] as $attempt) {
        $log = WebhookLog::query()->create(['gateway' => 'xendit', 'order_id' => $payment->order_id,
            'event_type' => 'settlement', 'status' => 'pending', 'payload' => [], 'headers' => []]);
        (new HandlePaymentWebhook($log, ['order_id' => $payment->order_id, 'status' => 'paid', 'amount' => 100000]))->handle();
    }
    expect(businessRecipients('payment_confirmed'))->toBe([$project->client_id]);
    expect(businessRecipients('project_awaiting_assignment'))->toBe([$manager->id]);
});
