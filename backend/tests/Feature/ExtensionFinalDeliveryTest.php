<?php

use App\Enum\PaymentStatus;
use App\Enum\ProjectStatus;
use App\Models\BasePrice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\ProjectTrack;
use App\Models\Room;
use App\Models\TrackStageProgress;
use App\Models\User;
use Database\Seeders\StudioSettingSeeder;

test('recording extension is included in final settlement before the owner can access delivery', function () {
    $this->travelTo(now()->setDate(2026, 10, 24)->setTime(9, 0));
    $this->seed(StudioSettingSeeder::class);
    config(['payment.default_gateway' => 'midtrans']);
    $client = User::factory()->client()->create();
    $operator = User::factory()->operator()->create();
    $manager = User::factory()->manager()->create();
    $project = Project::factory()->create(['client_id' => $client->id, 'status' => ProjectStatus::InProgress,
        'price' => 1000000, 'po_amount' => 400000, 'package_snapshot' => ['stages' => ['recording']]]);
    Payment::factory()->paid()->create(['project_id' => $project->id, 'amount' => 400000]);
    $stage = $project->stages()->create(['stage' => 'recording', 'sequence' => 1, 'operator_id' => $operator->id,
        'status' => 'in_progress', 'started_at' => now(), 'deadline_at' => now()->addDays(3)]);
    $track = ProjectTrack::create(['project_id' => $project->id, 'title' => 'Track', 'position' => 1]);
    $progress = TrackStageProgress::create(['project_stage_id' => $stage->id, 'project_track_id' => $track->id, 'status' => 'pending']);
    $room = Room::factory()->create();
    $price = BasePrice::create(['stage' => 'recording', 'unit' => 'hour', 'price' => 150000]);

    $this->actingAs($operator)->postJson("/api/v1/operator/stages/{$stage->id}/recording-extensions", [
        'room_id' => $room->id, 'start_at' => '2026-10-25T10:00:00+07:00', 'end_at' => '2026-10-25T11:01:00+07:00',
    ])->assertCreated()->assertJsonPath('data.extra_fee', 300000);
    $price->update(['price' => 250000]);
    $this->postJson("/api/v1/operator/track-progress/{$progress->id}/tracks/{$track->id}/submissions", [
        'result_url' => 'https://example.com/final-track',
    ])->assertCreated();
    $submission = $progress->submissions()->sole();
    $this->actingAs($manager)->postJson("/api/v1/manager/track-progress/{$progress->id}/submissions/{$submission->id}/review", ['decision' => 'approve'])->assertOk();
    $payment = $project->payments()->where('type', 'final')->sole();
    expect($payment->amount)->toBe(900000);
    expect($project->fresh()->final_payment_amount)->toBe(900000);
    $this->actingAs($client)->getJson("/api/v1/client/projects/{$project->id}/final-delivery")->assertForbidden();
    $this->postJson("/api/v1/client/payments/{$payment->id}/final/initiate", ['provider' => 'cash'])
        ->assertOk()->assertJsonPath('data.status', 'pending_manual_confirmation');
    $this->actingAs($manager)->postJson("/api/v1/manager/payments/{$payment->id}/confirm", ['note' => 'Tunai diterima'])->assertOk();
    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid);
    expect($payment->fresh()->invoice->status->value)->toBe('paid');
    expect($project->fresh()->status)->toBe(ProjectStatus::Completed);
    $this->actingAs($client)->getJson("/api/v1/client/projects/{$project->id}/final-delivery")
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.result_url', 'https://example.com/final-track');
    $this->actingAs(User::factory()->client()->create())->getJson("/api/v1/client/projects/{$project->id}/final-delivery")->assertForbidden();
});
