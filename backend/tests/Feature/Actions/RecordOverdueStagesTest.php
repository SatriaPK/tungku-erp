<?php

use App\Actions\Project\ConfirmStageDeadline;
use App\Actions\Project\RecordOverdueStages;
use App\Enum\OperatorActivityType;
use App\Enum\ProjectStatus;
use App\Enum\StageStatus;
use App\Enum\StageType;
use App\Models\OperatorActivity;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/** @return array{Project, ProjectStage} */
function overdueFixture(): array
{
    $operator = User::factory()->operator()->create();
    $project = Project::factory()->create(['status' => ProjectStatus::InProgress, 'created_at' => '2020-01-01']);
    $stage = $project->stages()->create(['stage' => StageType::Editing, 'sequence' => 1, 'status' => StageStatus::Ready, 'operator_id' => $operator->id]);
    app(ConfirmStageDeadline::class)->handle($stage, $operator, CarbonImmutable::parse('2026-10-16 10:00:00', 'Asia/Jakarta'));

    return [$project, $stage->refresh()];
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00:00', 'Asia/Jakarta'));
});

it('detects strictly after the deadline and preserves its original system event', function () {
    [$project, $stage] = overdueFixture();
    $recorder = app(RecordOverdueStages::class);
    expect($recorder->recordStage($stage->id))->toBeFalse();
    $this->travelTo(CarbonImmutable::parse('2026-10-16 10:00:00', 'Asia/Jakarta'));
    expect($recorder->recordStage($stage->id))->toBeFalse()->and($recorder->handle()['recorded'])->toBe(0);
    $replacement = User::factory()->operator()->create(['name' => '<b>Pengganti</b>']);
    $stage->update(['operator_id' => $replacement->id]);
    $before = $stage->getRawOriginal();
    $this->travel(1)->seconds();
    expect($recorder->handle())->toBe(['examined' => 1, 'recorded' => 1, 'skipped' => 0]);
    $event = OperatorActivity::where('type', OperatorActivityType::StageOverdue)->sole();
    expect($event->actor_id)->toBeNull()->and($event->actor_name)->toBeNull()
        ->and($event->operator_id)->toBe($replacement->id)->and($event->operator_name)->toBe('Pengganti')
        ->and($event->occurred_at->toDateTimeString())->toBe('2026-10-16 10:00:01')
        ->and($event->recorded_at->equalTo($event->occurred_at))->toBeTrue()
        ->and($event->deadline_at->toDateTimeString())->toBe('2026-10-16 10:00:00')
        ->and($stage->refresh()->getRawOriginal())->toBe($before);
    $original = $event->getRawOriginal();
    $this->travel(1)->days();
    $stage->update(['operator_id' => null]);
    expect($recorder->recordStage($stage->id))->toBeFalse()
        ->and($recorder->handle()['examined'])->toBe(0)
        ->and($event->refresh()->getRawOriginal())->toBe($original);
    $stage->update(['status' => StageStatus::Completed, 'completed_at' => now()]);
    expect($recorder->recordStage($stage->id))->toBeFalse()->and($event->refresh()->getRawOriginal())->toBe($original);
});

it('rechecks stale candidates and excludes ineligible stages', function (array $stageChanges, array $projectChanges, bool $removeMarker) {
    [$project, $stage] = overdueFixture();
    $stage->update($stageChanges);
    $project->update($projectChanges);
    if ($removeMarker) {
        DB::table('operator_activities')->delete();
    }
    $this->travel(2)->days();
    expect(app(RecordOverdueStages::class)->recordStage($stage->id))->toBeFalse()
        ->and(app(RecordOverdueStages::class)->handle()['examined'])->toBe(0)
        ->and(OperatorActivity::where('type', OperatorActivityType::StageOverdue)->count())->toBe(0);
})->with([
    'legacy without marker' => [[], [], true],
    'changed deadline' => [['deadline_at' => '2026-10-16 11:00:00'], [], false],
    'null deadline' => [['deadline_at' => null], [], false],
    'recording' => [['stage' => StageType::Recording], [], false],
    'locked' => [['status' => StageStatus::Locked], [], false],
    'ready' => [['status' => StageStatus::Ready], [], false],
    'completed first' => [['status' => StageStatus::Completed], [], false],
    'stage completion timestamp' => [['completed_at' => '2026-10-16 11:00:00'], [], false],
    'project completion timestamp' => [[], ['completed_at' => '2026-10-16 11:00:00'], false],
    'pending payment' => [[], ['status' => ProjectStatus::PendingPayment], false],
    'awaiting assignment' => [[], ['status' => ProjectStatus::AwaitingAssignment], false],
    'awaiting final payment' => [[], ['status' => ProjectStatus::AwaitingFinalPayment], false],
    'completed project' => [[], ['status' => ProjectStatus::Completed], false],
    'cancelled project' => [[], ['status' => ProjectStatus::Cancelled], false],
]);

it('skips deleted candidates and allows a null associated operator', function () {
    [$project, $stage] = overdueFixture();
    $stage->update(['operator_id' => null]);
    $this->travel(2)->days();
    expect(app(RecordOverdueStages::class)->recordStage($stage->id))->toBeTrue();
    $event = OperatorActivity::where('type', OperatorActivityType::StageOverdue)->sole();
    expect($event->operator_id)->toBeNull()->and($event->operator_name)->toBeNull();
    $stage->delete();
    expect(app(RecordOverdueStages::class)->recordStage($stage->id))->toBeFalse()
        ->and($event->refresh()->source_id)->toBe($stage->id);
});

it('rolls back a failed event write and records it on retry', function () {
    [$project, $stage] = overdueFixture();
    $this->travel(2)->days();
    Event::listen('eloquent.created: '.OperatorActivity::class, function (OperatorActivity $event): void {
        if ($event->type === OperatorActivityType::StageOverdue) {
            throw new RuntimeException('Ledger failure');
        }
    });
    try {
        expect(fn () => app(RecordOverdueStages::class)->handle())->toThrow(RuntimeException::class, 'Ledger failure');
    } finally {
        Event::forget('eloquent.created: '.OperatorActivity::class);
    }
    expect(OperatorActivity::count())->toBe(1)->and($stage->refresh()->status)->toBe(StageStatus::InProgress)
        ->and(app(RecordOverdueStages::class)->handle()['recorded'])->toBe(1);
});

it('processes every batch and exposes only counters from the scheduled command', function () {
    for ($index = 0; $index < 201; $index++) {
        overdueFixture();
    }
    $this->travel(2)->days();
    $this->artisan('operator-activities:record-overdue')
        ->expectsOutput('Examined: 201; recorded: 201; skipped: 0.')->assertSuccessful();
    $this->artisan('operator-activities:record-overdue')
        ->expectsOutput('Examined: 0; recorded: 0; skipped: 0.')->assertSuccessful();
    expect(OperatorActivity::where('type', OperatorActivityType::StageOverdue)->count())->toBe(201);
    $event = collect(app(Schedule::class)->events())->sole(fn ($event): bool => str_contains($event->command ?? '', 'operator-activities:record-overdue'));
    expect($event->expression)->toBe('* * * * *')->and($event->withoutOverlapping)->toBeTrue();
});
