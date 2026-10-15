<?php

use App\Actions\Project\ConfirmStageDeadline;
use App\Enum\ProjectStatus;
use App\Enum\StageStatus;
use App\Models\Package;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

function createReadyStage(User $operator): ProjectStage
{
    $client = User::factory()->client()->create();
    $package = Package::factory()->create([
        'include_recording' => false,
        'include_editing' => true,
        'include_mixing' => false,
        'include_mastering' => false,
    ]);
    $project = Project::factory()->create([
        'client_id' => $client->id,
        'package_id' => $package->id,
        'status' => ProjectStatus::InProgress,
        'package_snapshot' => ['stages' => ['editing']],
    ]);

    return ProjectStage::query()->create([
        'project_id' => $project->id,
        'stage' => 'editing',
        'sequence' => 2,
        'operator_id' => $operator->id,
        'status' => StageStatus::Ready,
        'assigned_at' => now(),
    ]);
}

it('starts the assigned ready stage and stores equivalent deadlines in Jakarta', function (string $deadline) {
    $operator = User::factory()->operator()->create();
    $stage = createReadyStage($operator);
    $this->travelTo('2026-10-10 10:00:00 Asia/Jakarta');

    $response = $this->actingAs($operator)->postJson("/api/v1/operator/stages/{$stage->id}/confirm-deadline", [
        'deadline_at' => $deadline,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.status', StageStatus::InProgress->value)
        ->assertJsonPath('data.deadline_at', '2026-10-20T18:30:00+07:00')
        ->assertJsonPath('data.started_at', '2026-10-10T10:00:00+07:00');

    $this->assertDatabaseHas('project_stages', [
        'id' => $stage->id,
        'status' => StageStatus::InProgress->value,
        'deadline_at' => '2026-10-20 18:30:00',
        'started_at' => '2026-10-10 10:00:00',
    ]);
})->with(['2026-10-20T18:30:00+07:00', '2026-10-20T11:30:00Z', '2026-10-20 18:30:00']);

it('normalizes deadlines in the action for non HTTP callers', function () {
    $operator = User::factory()->operator()->create();
    $stage = createReadyStage($operator);
    $this->travelTo('2026-10-10 10:00:00 Asia/Jakarta');

    app(ConfirmStageDeadline::class)->handle(
        $stage, $operator, CarbonImmutable::parse('2026-10-20T11:30:00Z'),
    );

    $this->assertDatabaseHas('project_stages', [
        'id' => $stage->id,
        'deadline_at' => '2026-10-20 18:30:00',
    ]);
});

it('rejects non future deadlines in the action without changing the stage', function (string $deadline) {
    $this->travelTo('2026-10-10 10:00:00 Asia/Jakarta');
    $operator = User::factory()->operator()->create();
    $stage = createReadyStage($operator);
    $originalAttributes = $stage->fresh()->getRawOriginal();

    try {
        app(ConfirmStageDeadline::class)->handle($stage, $operator, CarbonImmutable::parse($deadline));
        $this->fail('Expected a non-future deadline to be rejected.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe([
            'deadline_at' => ['Tenggat harus berada di masa depan.'],
        ]);
    }

    expect($stage->fresh()->getRawOriginal())->toBe($originalAttributes);
})->with([
    'past local' => '2026-10-10 09:59:59',
    'past Jakarta offset' => '2026-10-10T09:59:59+07:00',
    'past UTC' => '2026-10-10T02:59:59Z',
    'past with later wall clock' => '2026-10-10T11:59:59+09:00',
    'equal local' => '2026-10-10 10:00:00',
    'equal Jakarta offset' => '2026-10-10T10:00:00+07:00',
    'equal UTC' => '2026-10-10T03:00:00Z',
    'equal negative offset' => '2026-10-09T23:00:00-04:00',
]);

it('returns 422 and preserves the stage when another operator confirms its deadline', function () {
    $operator = User::factory()->operator()->create();
    $stage = createReadyStage(User::factory()->operator()->create());

    $this->actingAs($operator)->postJson("/api/v1/operator/stages/{$stage->id}/confirm-deadline", [
        'deadline_at' => now()->addDays(5)->toIso8601String(),
    ])->assertUnprocessable()->assertJsonValidationErrors('stage');

    expect($stage->fresh()->status)->toBe(StageStatus::Ready)
        ->and($stage->fresh()->started_at)->toBeNull()
        ->and($stage->fresh()->deadline_at)->toBeNull();
});

it('returns 422 when the stage is locked or an earlier stage is incomplete', function () {
    $operator = User::factory()->operator()->create();
    $stage = createReadyStage($operator);
    $stage->update(['status' => StageStatus::Locked]);

    $this->actingAs($operator)->postJson("/api/v1/operator/stages/{$stage->id}/confirm-deadline", [
        'deadline_at' => now()->addDays(5)->toIso8601String(),
    ])->assertUnprocessable();

    $stage->update(['status' => StageStatus::Ready]);
    ProjectStage::query()->create([
        'project_id' => $stage->project_id,
        'stage' => 'recording',
        'sequence' => 1,
        'status' => StageStatus::Locked,
    ]);

    $this->actingAs($operator)->postJson("/api/v1/operator/stages/{$stage->id}/confirm-deadline", [
        'deadline_at' => now()->addDays(5)->toIso8601String(),
    ])->assertUnprocessable();

    expect($stage->fresh()->status)->toBe(StageStatus::Ready)
        ->and($stage->fresh()->started_at)->toBeNull();
});

it('returns 422 for missing malformed and past deadlines', function (mixed $deadline) {
    $operator = User::factory()->operator()->create();
    $stage = createReadyStage($operator);
    $payload = $deadline === null ? [] : ['deadline_at' => $deadline];

    $this->actingAs($operator)->postJson("/api/v1/operator/stages/{$stage->id}/confirm-deadline", $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('deadline_at');

    expect($stage->fresh()->status)->toBe(StageStatus::Ready)
        ->and($stage->fresh()->started_at)->toBeNull();
})->with([
    'missing' => null,
    'malformed' => 'not-a-date',
    'past' => '2020-01-01T00:00:00Z',
]);

it('returns 422 for a repeated confirmation without changing the original start time', function () {
    $operator = User::factory()->operator()->create();
    $stage = createReadyStage($operator);
    $this->travelTo('2026-10-10 10:00:00 Asia/Jakarta');

    $this->actingAs($operator)->postJson("/api/v1/operator/stages/{$stage->id}/confirm-deadline", [
        'deadline_at' => '2026-10-20T00:00:00Z',
    ])->assertOk();
    $originalStartedAt = $stage->fresh()->started_at->toDateTimeString();

    $this->travelTo('2026-10-11 10:00:00 Asia/Jakarta');
    $this->actingAs($operator)->postJson("/api/v1/operator/stages/{$stage->id}/confirm-deadline", [
        'deadline_at' => '2026-10-21T00:00:00Z',
    ])->assertUnprocessable();

    expect($stage->fresh()->started_at->toDateTimeString())->toBe($originalStartedAt)
        ->and($stage->fresh()->deadline_at->toDateTimeString())->toBe('2026-10-20 07:00:00');
});

it('forbids a manager from confirming an operator deadline', function () {
    $manager = User::factory()->manager()->create();
    $stage = createReadyStage(User::factory()->operator()->create());

    $this->actingAs($manager)->postJson("/api/v1/operator/stages/{$stage->id}/confirm-deadline", [
        'deadline_at' => now()->addDays(5)->toIso8601String(),
    ])->assertForbidden();

    expect($stage->fresh()->status)->toBe(StageStatus::Ready);
});
