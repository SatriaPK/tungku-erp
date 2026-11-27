<?php

use App\Actions\Booking\HoldSlot;
use App\Actions\Project\ConfirmStageDeadline;
use App\Actions\Project\ReviewTrackResult;
use App\Actions\Project\SubmitTrackResult;
use App\Actions\Quotation\RespondToQuotation;
use App\Actions\Quotation\SubmitQuotation;
use App\Contracts\PaymentGatewayInterface;
use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;
use App\Enum\TrackResultReviewDecision;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\RecordingSession;
use App\Models\Room;
use App\Models\StudioSetting;
use App\Models\TrackStageProgress;
use App\Models\User;
use App\Services\Payment\PaymentGatewayFactory;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

afterEach(function () {
    PaymentGatewayFactory::clearFake();
});

test('inactive accounts cannot read the legacy user endpoint but can log out', function () {
    $user = User::factory()->client()->inactive()->create();

    config(['sanctum.stateful' => ['localhost:5173']]);
    $this->withHeader('Origin', 'http://localhost:5173');
    $this->actingAs($user)->getJson('/api/user')->assertForbidden();
    $this->postJson('/api/v1/auth/logout')->assertSuccessful();
});

test('non clients cannot create holds through the client endpoint', function (string $role) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)->postJson('/api/v1/client/bookings/hold', [])->assertForbidden();

    $this->assertDatabaseCount('recording_sessions', 0);
})->with(['manager', 'operator']);

test('invoice viewing permission does not grant permission to initiate payment', function () {
    $manager = User::factory()->manager()->create();
    $invoice = Invoice::factory()->create();

    $this->actingAs($manager)->getJson("/api/v1/client/invoices/{$invoice->id}")->assertOk();
    $this->postJson("/api/v1/client/invoices/{$invoice->id}/initiate-payment", ['gateway' => 'midtrans'])->assertForbidden();

    $this->assertDatabaseCount('payments', 0);
});

test('clients cannot confirm a held session before payment is paid', function (?string $status) {
    $this->freezeTime();
    $client = User::factory()->client()->create();
    $session = RecordingSession::factory()->for($client, 'client')->held()->create();
    if ($status !== null) {
        $invoice = Invoice::factory()->for($client, 'client')->create(['recording_session_id' => $session->id]);
        $payment = Payment::factory()->cash()->forInvoice($invoice)->create(['status' => $status]);
        $session->update(['payment_id' => $payment->id]);
    }

    $this->actingAs($client)->postJson('/api/v1/client/bookings/confirm', ['recording_session_id' => $session->id])
        ->assertUnprocessable();

    $this->assertDatabaseHas('recording_sessions', ['id' => $session->id, 'status' => 'held']);
})->with(['no payment' => [null], 'cash awaiting manager' => ['pending_manual_confirmation'], 'gateway pending' => ['pending']]);

test('direct booking actions reject inactive clients', function () {
    $client = User::factory()->client()->inactive()->create();
    $room = Room::factory()->create();

    expect(fn () => app(HoldSlot::class)->handle($room->id, $client->id, Carbon::tomorrow()->setTime(10, 0), Carbon::tomorrow()->setTime(11, 0)))
        ->toThrow(InvalidArgumentException::class, 'active client');

    $this->assertDatabaseCount('recording_sessions', 0);
});

test('clients cannot request refunds or call payment providers', function (string $provider) {
    $payment = Payment::factory()->paid()->create(['provider' => $provider]);
    $gateway = Mockery::mock(PaymentGatewayInterface::class);
    $gateway->shouldNotReceive('processRefund');
    PaymentGatewayFactory::fake($gateway);
    Notification::fake();

    $this->actingAs(User::factory()->client()->create())
        ->postJson('/api/v1/manager/payments/'.$payment->id.'/refund', [
            'amount' => $payment->amount, 'reason' => 'Unauthorized request',
        ])->assertForbidden();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid);
    Notification::assertNothingSent();
})->with(array_map(fn (PaymentProvider $provider): string => $provider->value, PaymentProvider::cases()));

test('inactive actors cannot mutate production through direct actions', function (string $operation) {
    $operator = User::factory()->operator()->inactive()->create();
    $manager = User::factory()->manager()->inactive()->create();
    $project = Project::factory()->create(['status' => 'in_progress']);
    $stage = $project->stages()->create(['stage' => 'editing', 'sequence' => 1, 'status' => 'ready', 'operator_id' => $operator->id]);
    $track = $project->tracks()->create(['title' => 'Track', 'position' => 1]);
    $progress = TrackStageProgress::query()->create([
        'project_stage_id' => $stage->id, 'project_track_id' => $track->id,
        'status' => $operation === 'review' ? 'in_review' : 'pending', 'current_version' => 1,
    ]);
    $submission = $progress->submissions()->create(['version' => 1, 'result_url' => 'https://example.com/result', 'submitted_by' => $operator->id, 'submitted_at' => now()]);

    $mutate = match ($operation) {
        'deadline' => fn () => app(ConfirmStageDeadline::class)->handle($stage, $operator, CarbonImmutable::tomorrow()),
        'submit' => fn () => app(SubmitTrackResult::class)->handle($progress, $track, $operator, 'https://example.com/new'),
        'review' => fn () => app(ReviewTrackResult::class)->handle($progress, $submission, $manager, TrackResultReviewDecision::Approve, null),
    };
    expect($mutate)->toThrow(ValidationException::class);

    expect($stage->fresh()->deadline_at)->toBeNull();
    expect($progress->fresh()->getRawOriginal('status'))->toBe($operation === 'review' ? 'in_review' : 'pending');
    $this->assertDatabaseCount('track_stage_submissions', 1);
    $this->assertDatabaseCount('operator_activities', 0);
})->with(['deadline', 'submit', 'review']);

test('quotation actions reject inactive owners without adding offers', function () {
    $client = User::factory()->client()->inactive()->create();
    $quotation = Quotation::query()->create(['client_id' => $client->id, 'status' => 'awaiting_client']);

    expect(fn () => app(SubmitQuotation::class)->handle($client, []))
        ->toThrow(ValidationException::class, 'active clients');
    expect(fn () => app(RespondToQuotation::class)->handle($quotation, $client, []))
        ->toThrow(RuntimeException::class, 'tidak berwenang');

    $this->assertDatabaseCount('quotations', 1);
    $this->assertDatabaseCount('quotation_offers', 0);
});

test('inactive managers are refused by discovered and mapped policies', function () {
    $manager = User::factory()->manager()->inactive()->create();
    $invoice = Invoice::factory()->create();

    expect(Gate::forUser($manager)->allows('view', $invoice))->toBeFalse();
    expect(Gate::forUser($manager)->allows('update', StudioSetting::class))->toBeFalse();
    $manager->forceFill(['is_active' => true])->save();
    expect(Gate::forUser($manager)->allows('update', StudioSetting::class))->toBeTrue();
});

test('direct booking rejects foreign projects and mismatched stages', function (string $case) {
    $client = User::factory()->client()->create();
    $room = Room::factory()->create();
    $project = Project::factory()->for($client, 'client')->create();
    $otherProject = Project::factory()->create();
    $stageProject = $case === 'non recording stage' ? $project : $otherProject;
    $stage = $stageProject->stages()->create(['stage' => $case === 'non recording stage' ? 'editing' : 'recording', 'sequence' => 1, 'status' => 'ready']);

    expect(fn () => app(HoldSlot::class)->handle(
        $room->id, $client->id, Carbon::tomorrow()->setTime(10, 0), Carbon::tomorrow()->setTime(11, 0),
        projectId: $case === 'foreign project' ? $otherProject->id : ($case === 'stage without project' ? null : $project->id),
        projectStageId: in_array($case, ['foreign project', 'quotation with existing project'], true) ? null : $stage->id,
        quotationId: $case === 'quotation with existing project' ? 999 : null,
    ))->toThrow(InvalidArgumentException::class);

    $this->assertDatabaseCount('recording_sessions', 0);
    $this->assertDatabaseCount('invoices', 0);
})->with(['foreign project', 'mismatched stage', 'stage without project', 'non recording stage', 'quotation with existing project']);
