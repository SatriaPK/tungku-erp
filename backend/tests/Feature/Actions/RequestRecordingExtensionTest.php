<?php

use App\Actions\Booking\RequestRecordingExtension;
use App\Enum\ProjectStatus;
use App\Enum\SessionStatus;
use App\Enum\StageStatus;
use App\Enum\StageType;
use App\Models\BasePrice;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\RecordingSession;
use App\Models\Room;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\StudioSettingSeeder;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Assert;

function recordingExtensionFixture(array $stageAttributes = []): array
{
    test()->seed(StudioSettingSeeder::class);

    $client = User::factory()->client()->create();
    $operator = User::factory()->operator()->create();
    $project = Project::factory()->create([
        'client_id' => $client->id,
        'status' => ProjectStatus::InProgress,
    ]);
    $stage = ProjectStage::query()->create(array_merge([
        'project_id' => $project->id,
        'stage' => StageType::Recording,
        'sequence' => 1,
        'operator_id' => $operator->id,
        'status' => StageStatus::InProgress,
        'started_at' => now()->subHour(),
    ], $stageAttributes));
    $room = Room::factory()->create();

    BasePrice::query()->create([
        'stage' => StageType::Recording,
        'unit' => 'hour',
        'price' => 150_000,
    ]);

    return [$client, $operator, $project, $stage, $room];
}

function recordingExtensionAction(ProjectStage $stage, User $operator, int $roomId, CarbonImmutable $startAt, CarbonImmutable $endAt): RecordingSession
{
    return app(RequestRecordingExtension::class)->handle($stage, $operator, $roomId, $startAt, $endAt);
}

function createRecordingExtension(ProjectStage $stage, User $operator, Room $room, int $minutes = 61): RecordingSession
{
    $startAt = CarbonImmutable::now('Asia/Jakarta')->addDay()->setTime(10, 0);

    return recordingExtensionAction(
        $stage,
        $operator,
        $room->id,
        $startAt,
        $startAt->addMinutes($minutes),
    );
}

function expectRecordingExtensionValidation(callable $callback): void
{
    try {
        $callback();
    } catch (ValidationException $exception) {
        Assert::assertInstanceOf(ValidationException::class, $exception);

        return;
    }

    test()->fail('Expected a recording-extension validation exception.');
}

test('snapshots extension fee using the current recording hourly rate', function () {
    [$client, $operator, $project, $stage, $room] = recordingExtensionFixture();

    $extension = createRecordingExtension($stage, $operator, $room);

    BasePrice::query()->where('stage', StageType::Recording)->update(['price' => 250_000]);

    expect($extension->fresh()->extra_fee)->toBe(300_000)
        ->and($extension->project_id)->toBe($project->id)
        ->and($extension->project_stage_id)->toBe($stage->id)
        ->and($extension->client_id)->toBe($client->id)
        ->and($extension->requested_by)->toBe($operator->id)
        ->and($extension->is_extension)->toBeTrue()
        ->and($extension->status)->toBe(SessionStatus::Confirmed)
        ->and($project->fresh()->extensionFeeTotal())->toBe(300_000);
});

test('rounds positive extension duration up to a full billable hour', function (int $minutes, int $expectedFee) {
    [, $operator, , $stage, $room] = recordingExtensionFixture();

    expect(createRecordingExtension($stage, $operator, $room, $minutes)->extra_fee)->toBe($expectedFee);
})->with([
    'one minute' => [1, 150_000],
    'exactly one hour' => [60, 150_000],
    'one hour and one minute' => [61, 300_000],
]);

test('rejects an extension requested by an operator not assigned to the recording stage', function () {
    [, , , $stage, $room] = recordingExtensionFixture();
    $otherOperator = User::factory()->operator()->create();

    expectRecordingExtensionValidation(fn () => createRecordingExtension($stage, $otherOperator, $room));
});

test('rejects an inactive assigned operator', function () {
    [, $operator, , $stage, $room] = recordingExtensionFixture();
    $operator = User::factory()->operator()->inactive()->create();
    $stage->update(['operator_id' => $operator->id]);

    expectRecordingExtensionValidation(fn () => createRecordingExtension($stage, $operator, $room));
});

test('rejects projects that are not in progress', function (ProjectStatus $status) {
    [, $operator, $project, $stage, $room] = recordingExtensionFixture();
    $project->status = $status;
    $project->save();
    expectRecordingExtensionValidation(fn () => createRecordingExtension($stage, $operator, $room));
})->with([
    'awaiting final payment' => [ProjectStatus::AwaitingFinalPayment],
    'completed' => [ProjectStatus::Completed],
    'cancelled' => [ProjectStatus::Cancelled],
]);

test('rejects a recording stage that is ready but not started or already completed', function (StageStatus $status) {
    [, $operator, , $stage, $room] = recordingExtensionFixture(['status' => $status]);

    expectRecordingExtensionValidation(fn () => createRecordingExtension($stage, $operator, $room));
})->with([
    'ready' => [StageStatus::Ready],
    'completed' => [StageStatus::Completed],
    'locked' => [StageStatus::Locked],
]);

test('rejects a non-recording stage', function () {
    [, $operator, , $stage, $room] = recordingExtensionFixture();
    $stage->update(['stage' => StageType::Editing]);

    expectRecordingExtensionValidation(fn () => createRecordingExtension($stage, $operator, $room));
});

test('rejects an expired recording stage', function () {
    [, $operator, , $stage, $room] = recordingExtensionFixture(['deadline_at' => CarbonImmutable::now('Asia/Jakarta')->subMinute()]);

    expectRecordingExtensionValidation(fn () => createRecordingExtension($stage, $operator, $room));
});

test('rejects an extension ending after the recording stage deadline', function () {
    $startAt = CarbonImmutable::now('Asia/Jakarta')->addDay()->setTime(10, 0);
    $deadlineAt = $startAt->addMinutes(30);
    [, $operator, , $stage, $room] = recordingExtensionFixture(['deadline_at' => $deadlineAt]);
    expectRecordingExtensionValidation(fn () => recordingExtensionAction($stage, $operator, $room->id, $startAt, $startAt->addMinutes(61)));
});

test('rejects non-positive duration without inserting a session', function () {
    [, $operator, , $stage, $room] = recordingExtensionFixture();
    $start = CarbonImmutable::parse('2026-10-12 10:00:00', 'Asia/Jakarta');

    expectRecordingExtensionValidation(fn () => recordingExtensionAction($stage, $operator, $room->id, $start, $start));
});

test('rejects a room that does not exist', function () {
    [, $operator, , $stage] = recordingExtensionFixture();
    $start = CarbonImmutable::parse('2026-10-12 10:00:00', 'Asia/Jakarta');

    expectRecordingExtensionValidation(fn () => recordingExtensionAction($stage, $operator, 987654, $start, $start->addHour()));
});

test('rejects a slot that has already started without inserting a session', function () {
    [, $operator, , $stage, $room] = recordingExtensionFixture();
    $start = CarbonImmutable::now('Asia/Jakarta')->subMinute();

    expectRecordingExtensionValidation(fn () => recordingExtensionAction($stage, $operator, $room->id, $start, $start->addHour()));

    expect(RecordingSession::query()->count())->toBe(0);
});

test('rejects an operator whose account became inactive before the request', function () {
    [, $operator, , $stage, $room] = recordingExtensionFixture();
    User::query()->whereKey($operator->id)->update(['is_active' => false]);

    expectRecordingExtensionValidation(fn () => createRecordingExtension($stage, $operator, $room));

    expect(RecordingSession::query()->count())->toBe(0);
});

test('rejects a stage whose deadline has been reached', function () {
    $deadlineAt = CarbonImmutable::now('Asia/Jakarta');
    [, $operator, , $stage, $room] = recordingExtensionFixture(['deadline_at' => $deadlineAt]);

    expectRecordingExtensionValidation(fn () => recordingExtensionAction(
        $stage,
        $operator,
        $room->id,
        $deadlineAt->addDay(),
        $deadlineAt->addDay()->addHour(),
    ));

    expect(RecordingSession::query()->count())->toBe(0);
});

test('rejects an inactive room', function () {
    [, $operator, , $stage, $room] = recordingExtensionFixture();
    $room->update(['is_active' => false]);

    expectRecordingExtensionValidation(fn () => createRecordingExtension($stage, $operator, $room));
});

test('rejects occupied and duplicate slots', function () {
    [, $operator, , $stage, $room] = recordingExtensionFixture();
    $start = CarbonImmutable::parse('2026-10-12 10:00:00', 'Asia/Jakarta');

    RecordingSession::factory()->for($room)->confirmed()->timeRange($start, $start->addHour())->create();

    expectRecordingExtensionValidation(fn () => recordingExtensionAction($stage, $operator, $room->id, $start, $start->addHour()));
});

test('rejects slots outside operating hours and intervals spanning a closed period', function (CarbonImmutable $start, CarbonImmutable $end) {
    [, $operator, , $stage, $room] = recordingExtensionFixture();

    expectRecordingExtensionValidation(fn () => recordingExtensionAction($stage, $operator, $room->id, $start, $end));
})->with([
    'before opening' => [CarbonImmutable::parse('2026-10-12 08:00:00', 'Asia/Jakarta'), CarbonImmutable::parse('2026-10-12 08:30:00', 'Asia/Jakarta')],
    'duration crosses closing time' => [CarbonImmutable::parse('2026-10-12 21:00:00', 'Asia/Jakarta'), CarbonImmutable::parse('2026-10-12 23:00:00', 'Asia/Jakarta')],
]);

test('rejects a missing recording hourly rate', function () {
    [, $operator, , $stage, $room] = recordingExtensionFixture();
    BasePrice::query()->where('stage', StageType::Recording)->delete();

    expectRecordingExtensionValidation(fn () => createRecordingExtension($stage, $operator, $room));
});

test('rejects a recording rate that is not hourly', function () {
    [, $operator, , $stage, $room] = recordingExtensionFixture();
    BasePrice::query()->where('stage', StageType::Recording)->update(['unit' => 'track']);

    expectRecordingExtensionValidation(fn () => createRecordingExtension($stage, $operator, $room));

    expect(RecordingSession::query()->count())->toBe(0);
});

test('rejects a negative duration without inserting a session', function () {
    [, $operator, , $stage, $room] = recordingExtensionFixture();
    $start = CarbonImmutable::now('Asia/Jakarta')->addDay();

    expectRecordingExtensionValidation(fn () => recordingExtensionAction($stage, $operator, $room->id, $start, $start->subMinute()));

    expect(RecordingSession::query()->count())->toBe(0);
});

test('rejects an inactive project without inserting a session', function (ProjectStatus $status) {
    [, $operator, $project, $stage, $room] = recordingExtensionFixture();
    $project->update(['status' => $status]);

    expectRecordingExtensionValidation(fn () => createRecordingExtension($stage, $operator, $room));

    expect(RecordingSession::query()->count())->toBe(0);
})->with([
    'awaiting assignment' => [ProjectStatus::AwaitingAssignment],
    'awaiting final payment' => [ProjectStatus::AwaitingFinalPayment],
    'completed' => [ProjectStatus::Completed],
    'cancelled' => [ProjectStatus::Cancelled],
]);

test('rejects a completed recording stage without inserting a session', function () {
    [, $operator, , $stage, $room] = recordingExtensionFixture(['status' => StageStatus::Completed]);

    expectRecordingExtensionValidation(fn () => createRecordingExtension($stage, $operator, $room));

    expect(RecordingSession::query()->count())->toBe(0);
});

test('rejects an inactive room when it becomes inactive before the request', function () {
    [, $operator, , $stage, $room] = recordingExtensionFixture();
    Room::query()->whereKey($room->id)->update(['is_active' => false]);

    expectRecordingExtensionValidation(fn () => createRecordingExtension($stage, $operator, $room));

    expect(RecordingSession::query()->count())->toBe(0);
});

test('does not charge cancelled extensions', function () {
    [, $operator, $project, $stage, $room] = recordingExtensionFixture();
    $extension = createRecordingExtension($stage, $operator, $room);
    $extension->update(['status' => SessionStatus::Cancelled]);

    expect($project->fresh()->extensionFeeTotal())->toBe(0);
});
