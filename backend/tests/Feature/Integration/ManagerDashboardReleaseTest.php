<?php

use App\Actions\Payment\ConfirmManualPayment;
use App\Actions\Project\ConfirmStageDeadline;
use App\Actions\Project\RecordOverdueStages;
use App\Actions\Project\ReviewTrackResult;
use App\Actions\Project\SubmitTrackResult;
use App\Enum\PaymentType;
use App\Enum\ProjectStatus;
use App\Enum\StageStatus;
use App\Enum\StageType;
use App\Enum\TrackResultReviewDecision;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use Carbon\CarbonImmutable;

it('keeps dashboard and final delivery consistent through revision overdue approval and settlement', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-16 10:00:00', 'Asia/Jakarta'));
    config(['payment.default_gateway' => 'cash']);
    $manager = User::factory()->manager()->create();
    $operator = User::factory()->operator()->create();
    $client = User::factory()->client()->create();
    $project = Project::factory()->create(['client_id' => $client->id, 'status' => ProjectStatus::AwaitingAssignment,
        'price' => 1000, 'po_amount' => 400, 'package_snapshot' => ['stages' => ['editing']]]);
    Payment::factory()->paid()->create(['project_id' => $project->id, 'amount' => 400, 'paid_at' => now()]);
    $stage = $project->stages()->create(['stage' => StageType::Editing, 'sequence' => 1, 'status' => StageStatus::Locked]);
    $track = $project->tracks()->create(['title' => 'Single', 'position' => 1]);
    $this->actingAs($manager)->postJson("/api/v1/manager/projects/{$project->id}/assignments", [
        'assignments' => ['editing' => $operator->id],
    ])->assertOk();
    app(ConfirmStageDeadline::class)->handle($stage, $operator, CarbonImmutable::now()->addHour());
    $progress = $stage->trackProgress()->sole();
    $first = app(SubmitTrackResult::class)->handle($progress, $track, $operator, 'https://private.example/v1');
    app(ReviewTrackResult::class)->handle($progress, $first, $manager, TrackResultReviewDecision::RequestRevision, 'Revisi');
    $second = app(SubmitTrackResult::class)->handle($progress, $track, $operator, 'https://private.example/v2');
    $this->travel(2)->hours();
    app(RecordOverdueStages::class)->handle();

    $this->actingAs($manager)->getJson('/api/v1/manager/dashboard')->assertOk()
        ->assertJsonPath('data.in_progress_projects.track_count', 1)
        ->assertJsonPath('data.revenue.current', 400)
        ->assertJsonCount(4, 'data.operator_activities');

    app(ReviewTrackResult::class)->handle($progress, $second, $manager, TrackResultReviewDecision::Approve, null);
    $this->actingAs($client)->getJson("/api/v1/client/projects/{$project->id}/final-delivery")->assertForbidden();
    $this->actingAs($manager)->getJson('/api/v1/manager/dashboard')->assertOk()
        ->assertJsonPath('data.in_progress_projects.count', 0)
        ->assertJsonPath('data.awaiting_final_payment.amount', 600)
        ->assertJsonPath('data.awaiting_final_payment.project_count', 1)
        ->assertJsonCount(4, 'data.operator_activities');
    $finalPayment = $project->payments()->where('type', PaymentType::Final)->sole();
    app(ConfirmManualPayment::class)->execute($finalPayment, 'Lunas');
    expect($project->refresh()->status)->toBe(ProjectStatus::Completed);
    $response = $this->actingAs($manager)->getJson('/api/v1/manager/dashboard')->assertOk()
        ->assertJsonPath('data.revenue.current', 1000)
        ->assertJsonPath('data.awaiting_final_payment.amount', 0)
        ->assertJsonPath('data.awaiting_final_payment.project_count', 0)
        ->assertJsonCount(4, 'data.operator_activities');
    expect($response->getContent())->not->toContain('private.example', 'result_url');
    $this->actingAs($client)->getJson("/api/v1/client/projects/{$project->id}/final-delivery")
        ->assertOk()->assertSee('private.example', false);
});
