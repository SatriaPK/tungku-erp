<?php

use App\Actions\Project\ConfirmStageDeadline;
use App\Actions\Project\RecordOperatorActivity;
use App\Actions\Project\SubmitTrackResult;
use App\Enum\OperatorActivityType;
use App\Enum\ProjectStatus;
use App\Enum\StageStatus;
use App\Enum\StageType;
use App\Enum\TrackProgressStatus;
use App\Models\OperatorActivity;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\ProjectTrack;
use App\Models\TrackStageProgress;
use App\Models\TrackStageSubmission;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\OperatorActivitySeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** @return array{User, Project, ProjectStage, ProjectTrack, TrackStageProgress} */
function activityFixture(): array
{
    $operator = User::factory()->operator()->create(['name' => '<b>Operator Awal</b>']);
    $project = Project::factory()->create(['status' => ProjectStatus::InProgress, 'name' => 'Project Lama', 'created_at' => '2020-01-01']);
    $stage = $project->stages()->create(['stage' => StageType::Editing, 'sequence' => 1, 'status' => StageStatus::Ready, 'operator_id' => $operator->id]);
    $track = $project->tracks()->create(['title' => 'Track Awal', 'position' => 1]);
    $progress = $stage->trackProgress()->create(['project_track_id' => $track->id, 'status' => TrackProgressStatus::Pending]);

    return [$operator, $project, $stage, $track, $progress];
}

it('records each submission version with its original actor and no result URL', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00:00', 'Asia/Jakarta'));
    [$operator, $project, $stage, $track, $progress] = activityFixture();
    $submission = app(SubmitTrackResult::class)->handle($progress, $track, $operator, 'https://private.example/v1');
    $event = OperatorActivity::sole();
    expect($event->type)->toBe(OperatorActivityType::ResultSubmitted)
        ->and($event->source_id)->toBe($submission->id)->and($event->submission_id)->toBe($submission->id)
        ->and($event->actor_id)->toBe($operator->id)->and($event->operator_id)->toBe($operator->id)
        ->and($event->actor_name)->toBe('Operator Awal')->and($event->project_name)->toBe('Project Lama')
        ->and($event->track_title)->toBe('Track Awal')->and($event->stage)->toBe(StageType::Editing)
        ->and($event->occurred_at->toDateTimeString())->toBe('2026-10-15 10:00:00')
        ->and(json_encode($event))->not->toContain('private.example', 'result_url', 'email', '<b>');
    $original = $event->getRawOriginal();
    expect(fn () => app(SubmitTrackResult::class)->handle($progress, $track, $operator, 'https://private.example/retry'))->toThrow(ValidationException::class);
    $replacement = User::factory()->operator()->create(['name' => 'Pengganti']);
    $stage->update(['operator_id' => $replacement->id]);
    $project->update(['name' => 'Renamed']);
    $track->update(['title' => 'Renamed track']);
    $this->travel(1)->hours();
    $same = app(RecordOperatorActivity::class)->resultSubmitted($submission, $project, $stage, $track);
    expect($same->getRawOriginal())->toBe($original);
    $progress->refresh()->update(['status' => TrackProgressStatus::Revision]);
    $revision = app(SubmitTrackResult::class)->handle($progress, $track, $replacement, 'https://private.example/v2');
    expect(OperatorActivity::count())->toBe(2)
        ->and(OperatorActivity::where('source_id', $revision->id)->sole()->actor_id)->toBe($replacement->id)
        ->and($event->refresh()->actor_id)->toBe($operator->id);
});

it('records one persistent deadline marker for a new action on an old project without backfill', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00:00', 'Asia/Jakarta'));
    [$operator, $project, $stage] = activityFixture();
    $historical = Project::factory()->create()->stages()->create(['stage' => StageType::Mixing, 'sequence' => 1,
        'status' => StageStatus::InProgress, 'started_at' => '2020-01-01', 'deadline_at' => '2020-01-02']);
    app(ConfirmStageDeadline::class)->handle($stage, $operator, CarbonImmutable::parse('2026-10-16T03:00:00Z'));
    $event = OperatorActivity::sole();
    expect($event->type)->toBe(OperatorActivityType::DeadlineConfirmed)
        ->and($event->source_id)->toBe($stage->id)->and($event->deadline_at->toDateTimeString())->toBe('2026-10-16 10:00:00')
        ->and($event->occurred_at->toDateTimeString())->toBe('2026-10-15 10:00:00')
        ->and($event->recorded_at->toDateTimeString())->toBe('2026-10-15 10:00:00')
        ->and(OperatorActivity::where('source_id', $historical->id)->count())->toBe(0);
    $original = $event->getRawOriginal();
    $this->travel(1)->days();
    $operator->update(['name' => 'Renamed operator']);
    $same = app(RecordOperatorActivity::class)->deadlineConfirmed($stage->refresh(), $project, $operator);
    expect($same->getRawOriginal())->toBe($original);
    expect(fn () => app(ConfirmStageDeadline::class)->handle($stage, $operator, CarbonImmutable::now()->addDay()))->toThrow(ValidationException::class);
    expect(OperatorActivity::count())->toBe(1);
});

it('rolls back the mutation and inserted event when the ledger writer fails', function (string $action) {
    $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00:00', 'Asia/Jakarta'));
    [$operator, , $stage, $track, $progress] = activityFixture();
    $eventName = 'eloquent.created: '.OperatorActivity::class;
    Event::listen($eventName, function (): never {
        throw new RuntimeException('ledger failure');
    });
    try {
        expect(function () use ($action, $operator, $stage, $track, $progress): void {
            if ($action === 'submission') {
                app(SubmitTrackResult::class)->handle($progress, $track, $operator, 'https://example.com/result');
            } else {
                app(ConfirmStageDeadline::class)->handle($stage, $operator, CarbonImmutable::now()->addDay());
            }
        })->toThrow(RuntimeException::class, 'ledger failure');
    } finally {
        Event::forget($eventName);
    }
    expect(OperatorActivity::count())->toBe(0)->and(TrackStageSubmission::count())->toBe(0)
        ->and($progress->refresh()->status)->toBe(TrackProgressStatus::Pending)
        ->and($progress->result_url)->toBeNull()->and($progress->current_version)->toBe(1)
        ->and($stage->refresh()->status)->toBe(StageStatus::Ready)->and($stage->deadline_at)->toBeNull()->and($stage->started_at)->toBeNull();
})->with(['submission', 'deadline']);

it('rolls back successful activity writes when an enclosing transaction fails', function () {
    [$operator, , $stage, $track, $progress] = activityFixture();
    expect(function () use ($operator, $stage, $track, $progress): void {
        DB::transaction(function () use ($operator, $stage, $track, $progress): void {
            app(ConfirmStageDeadline::class)->handle($stage, $operator, CarbonImmutable::now()->addDay());
            app(SubmitTrackResult::class)->handle($progress, $track, $operator, 'https://example.com/result');
            expect(OperatorActivity::count())->toBe(2);
            throw new RuntimeException('outer rollback');
        });
    })->toThrow(RuntimeException::class, 'outer rollback');
    expect(OperatorActivity::count())->toBe(0)->and(TrackStageSubmission::count())->toBe(0)
        ->and($stage->refresh()->deadline_at)->toBeNull();
});

it('rejects wrong operators and invalid domain states without events', function () {
    [$operator, $project, $stage, $track, $progress] = activityFixture();
    $foreign = User::factory()->operator()->create();
    expect(fn () => app(SubmitTrackResult::class)->handle($progress, $track, $foreign, 'https://example.com/result'))->toThrow(ValidationException::class);
    expect(fn () => app(ConfirmStageDeadline::class)->handle($stage, $foreign, CarbonImmutable::now()->addDay()))->toThrow(ValidationException::class);
    expect(fn () => app(ConfirmStageDeadline::class)->handle($stage, $operator, CarbonImmutable::now()->subDay()))->toThrow(ValidationException::class);
    $project->update(['status' => ProjectStatus::Cancelled]);
    expect(fn () => app(SubmitTrackResult::class)->handle($progress, $track, $operator, 'https://example.com/result'))->toThrow(ValidationException::class);
    expect(fn () => app(ConfirmStageDeadline::class)->handle($stage, $operator, CarbonImmutable::now()->addDay()))->toThrow(ValidationException::class);
    expect(OperatorActivity::count())->toBe(0);
});

it('retains identity and safe snapshots when subjects and actors are deleted', function () {
    [$operator, $project, $stage, $track, $progress] = activityFixture();
    app(ConfirmStageDeadline::class)->handle($stage, $operator, CarbonImmutable::now()->addDay());
    $submission = app(SubmitTrackResult::class)->handle($progress, $track, $operator, 'https://example.com/result');
    $project->delete();
    $operator->delete();
    $events = OperatorActivity::all();
    expect($events)->toHaveCount(2);
    foreach ($events as $event) {
        expect($event->actor_id)->toBeNull()->and($event->operator_id)->toBeNull()->and($event->project_id)->toBeNull()
            ->and($event->project_stage_id)->toBeNull()->and($event->submission_id)->toBeNull()->and($event->project_track_id)->toBeNull()
            ->and($event->actor_name)->toBe('Operator Awal')->and($event->project_name)->toBe('Project Lama');
    }
    expect($events->firstWhere('type', OperatorActivityType::ResultSubmitted)->source_id)->toBe($submission->id)
        ->and($events->firstWhere('type', OperatorActivityType::DeadlineConfirmed)->source_id)->toBe($stage->id);
});

it('enforces unique identities in the database and rejects model edits or deletion', function () {
    $event = OperatorActivity::factory()->create();
    expect(fn () => $event->update(['actor_name' => 'Replacement']))->toThrow(LogicException::class);
    expect(fn () => $event->delete())->toThrow(LogicException::class);
    $duplicate = $event->refresh()->getRawOriginal();
    unset($duplicate['id']);
    expect(fn () => DB::table('operator_activities')->insert($duplicate))->toThrow(UniqueConstraintViolationException::class);
    expect(OperatorActivity::count())->toBe(1);
});

it('creates and rolls back only the ledger schema without seeding historical activity', function () {
    [$operator, , $stage, , $progress] = activityFixture();
    $stage->update(['status' => StageStatus::InProgress, 'started_at' => now()->subYear(), 'deadline_at' => now()->subMonth()]);
    $progress->submissions()->create(['version' => 1, 'result_url' => 'https://example.com/legacy', 'submitted_by' => $operator->id, 'submitted_at' => now()->subYear()]);
    $migration = require database_path('migrations/2026_10_15_194429_create_operator_activities_table.php');
    $migration->down();
    expect(Schema::hasTable('operator_activities'))->toBeFalse()->and(TrackStageSubmission::count())->toBe(1);
    $migration->up();
    $this->seed(OperatorActivitySeeder::class);
    expect(OperatorActivity::count())->toBe(0)->and(Schema::hasColumn('operator_activities', 'updated_at'))->toBeFalse();
});
