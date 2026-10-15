<?php

use App\Actions\Project\AssignOperators;
use App\Actions\Project\ConfirmStageDeadline;
use App\Actions\Project\RecordOverdueStages;
use App\Actions\Project\ReviewTrackResult;
use App\Actions\Project\SubmitTrackResult;
use App\Enum\OperatorActivityType;
use App\Enum\ProjectStatus;
use App\Enum\StageStatus;
use App\Enum\StageType;
use App\Enum\TrackProgressStatus;
use App\Enum\TrackResultReviewDecision;
use App\Models\OperatorActivity;
use App\Models\Project;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

it('serializes overdue detection against competing transactions on isolated MySQL', function (string $winner, int $expectedCount, string $childResult) {
    if (getenv('PHASE17_MYSQL_TESTS') !== '1') {
        $this->markTestSkipped('Opt-in disposable MySQL at 127.0.0.1:33317 required.');
    }
    $observer = new PDO('mysql:host=127.0.0.1;port=33317', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $database = 'phase17_concurrency_test_'.getmypid();
    $observer->exec('CREATE DATABASE IF NOT EXISTS '.$database);
    $originalConnection = config('database.default');
    config(['database.default' => 'phase17_mysql', 'database.connections.phase17_mysql' => [
        'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 33317, 'database' => $database,
        'username' => 'root', 'password' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '', 'strict' => true,
    ]]);
    $worker = null;
    try {
        expect(Artisan::call('migrate:fresh', ['--database' => 'phase17_mysql', '--force' => true]))->toBe(0);
        $engine = $observer->query("SELECT ENGINE FROM information_schema.tables WHERE TABLE_SCHEMA='{$database}' AND TABLE_NAME='operator_activities'")->fetchColumn();
        expect($engine)->toBe('InnoDB');
        $this->travelTo(CarbonImmutable::now()->subDays(2));
        $operator = User::factory()->operator()->create();
        $project = Project::factory()->create(['status' => ProjectStatus::InProgress, 'package_snapshot' => ['stages' => ['editing']]]);
        $stage = $project->stages()->create(['stage' => StageType::Editing, 'sequence' => 1,
            'status' => StageStatus::Ready, 'operator_id' => $operator->id]);
        app(ConfirmStageDeadline::class)->handle($stage, $operator, CarbonImmutable::now()->addDay());
        $manager = User::factory()->manager()->create();
        $track = $project->tracks()->create(['title' => 'Track', 'position' => 1]);
        $progress = $stage->trackProgress()->create(['project_track_id' => $track->id, 'status' => TrackProgressStatus::Pending]);
        $submission = app(SubmitTrackResult::class)->handle($progress, $track, $operator, 'https://private.example/result');
        $workerProgress = $progress;
        $workerSubmission = $submission;
        if ($winner === 'two reviews') {
            $secondTrack = $project->tracks()->create(['title' => 'Second track', 'position' => 2]);
            $workerProgress = $stage->trackProgress()->create(['project_track_id' => $secondTrack->id, 'status' => TrackProgressStatus::Pending]);
            $workerSubmission = app(SubmitTrackResult::class)->handle($workerProgress, $secondTrack, $operator, 'https://private.example/second');
        }
        if ($winner === 'batch') {
            for ($index = 0; $index < 200; $index++) {
                $batchProject = Project::factory()->create(['status' => ProjectStatus::InProgress]);
                $batchStage = $batchProject->stages()->create(['stage' => StageType::Editing, 'sequence' => 1,
                    'status' => StageStatus::Ready, 'operator_id' => $operator->id]);
                app(ConfirmStageDeadline::class)->handle($batchStage, $operator, CarbonImmutable::now()->addDay());
            }
        }
        $this->travelBack();
        if ($winner === 'assignment') {
            $project->update(['status' => ProjectStatus::AwaitingAssignment]);
            $stage->refresh()->update(['status' => StageStatus::Locked, 'started_at' => null, 'deadline_at' => null]);
        }

        DB::beginTransaction();
        DB::table('project_stages')->where('id', $stage->id)->lockForUpdate()->first();
        DB::table('projects')->where('id', $project->id)->lockForUpdate()->first();
        if ($winner === 'unique') {
            expect(app(RecordOverdueStages::class)->recordStage($stage->id))->toBeTrue();
        }
        $workerCode = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!preg_match('/^phase17_concurrency_test_[0-9]+$/D', $argv[6])) {
    throw new RuntimeException('Unexpected test database');
}
config(['app.timezone' => 'Asia/Jakarta', 'database.default' => 'phase17_mysql',
'database.connections.phase17_mysql' => [
'driver'=>'mysql','host'=>'127.0.0.1','port'=>33317,'database'=>$argv[6],
'username'=>'root','password'=>'','charset'=>'utf8mb4','collation'=>'utf8mb4_unicode_ci','prefix'=>'','strict'=>true]]);
if ($argv[2] === 'assignment') {
    $stage = App\Models\ProjectStage::findOrFail($argv[1]);
    try {
        app(App\Actions\Project\AssignOperators::class)->handle($stage->project, ['editing'=>$stage->operator_id]);
        echo 'unexpected reassignment';
    } catch (Illuminate\Validation\ValidationException $exception) {
        echo 'assignment rejected';
    }
} elseif ($argv[2] === 'batch') {
    Illuminate\Support\Facades\Artisan::call('operator-activities:record-overdue');
    echo trim(Illuminate\Support\Facades\Artisan::output());
} elseif ($argv[2] === 'unique') {
    try {
        App\Models\OperatorActivity::create(['type'=>'stage_overdue', 'source_id'=>(int) $argv[1],
            'stage'=>'editing', 'occurred_at'=>now(), 'recorded_at'=>now()]);
        echo 'unexpected duplicate';
    } catch (Illuminate\Database\UniqueConstraintViolationException $exception) {
        echo 'duplicate rejected';
    }
} elseif ($argv[2] === 'review') {
    app(App\Actions\Project\ReviewTrackResult::class)->handle(
        App\Models\TrackStageProgress::findOrFail($argv[3]), App\Models\TrackStageSubmission::findOrFail($argv[4]),
        App\Models\User::findOrFail($argv[5]), App\Enum\TrackResultReviewDecision::Approve, null);
    echo 'approved';
} else {
    echo app(App\Actions\Project\RecordOverdueStages::class)->recordStage((int) $argv[1]) ? 'recorded' : 'skipped';
}
PHP;
        $worker = new Process([PHP_BINARY, '-r', $workerCode, (string) $stage->id,
            match ($winner) {
                'unique', 'batch' => $winner, 'detector before assignment' => 'assignment', 'detector before review', 'two reviews' => 'review', default => 'detect'
            },
            (string) $workerProgress->id, (string) $workerSubmission->id, (string) $manager->id, $database],
            base_path(), ['APP_ENV' => 'testing'], timeout: 40);
        $worker->start();
        $waitUntil = microtime(true) + 10;
        do {
            $waiting = (int) $observer->query("SELECT COUNT(*) FROM performance_schema.data_lock_waits w JOIN performance_schema.data_locks l ON l.ENGINE_LOCK_ID=w.REQUESTING_ENGINE_LOCK_ID WHERE l.OBJECT_SCHEMA='{$database}'")->fetchColumn() > 0;
            if ($waiting) {
                break;
            }
            if (! $worker->isRunning()) {
                throw new RuntimeException($worker->getErrorOutput().$worker->getOutput());
            }
            usleep(10000);
        } while (microtime(true) < $waitUntil);
        expect($waiting)->toBeTrue('The second connection must actually wait for the stage lock.');

        if ($winner === 'assignment') {
            app(AssignOperators::class)->handle($project, ['editing' => $operator->id]);
        } elseif ($winner === 'batch') {
            DB::enableQueryLog();
            try {
                expect(Artisan::call('operator-activities:record-overdue'))->toBe(0);
                expect(trim(Artisan::output()))->toBe('Examined: 201; recorded: 201; skipped: 0.');
                $candidateQuery = collect(DB::getQueryLog())->first(fn (array $query): bool => str_contains($query['query'], 'not exists'));
            } finally {
                DB::disableQueryLog();
                DB::flushQueryLog();
            }
            $plan = DB::select('EXPLAIN '.$candidateQuery['query'], $candidateQuery['bindings']);
            expect($plan)->not->toBeEmpty();
            file_put_contents(storage_path('logs/phase17-mysql-query-plan.json'), json_encode([
                'engine' => $observer->query('SELECT VERSION()')->fetchColumn(),
                'isolation' => $observer->query('SELECT @@transaction_isolation')->fetchColumn(),
                'eligible_stages' => 201, 'peak_memory_bytes' => memory_get_peak_usage(true), 'plan' => $plan,
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } elseif ($winner === 'completion') {
            $stage->refresh()->update(['status' => StageStatus::Completed, 'completed_at' => now()]);
        } elseif (in_array($winner, ['review', 'two reviews'], true)) {
            app(ReviewTrackResult::class)->handle($progress, $submission, $manager, TrackResultReviewDecision::Approve, null);
        } elseif ($winner === 'changed deadline') {
            $stage->refresh()->update(['deadline_at' => now()->subHour()]);
        } elseif ($winner !== 'unique') {
            expect(app(RecordOverdueStages::class)->recordStage($stage->id))->toBeTrue();
        }
        $firstEvent = OperatorActivity::where('type', OperatorActivityType::StageOverdue)->first();
        if ($winner === 'rollback') {
            DB::rollBack();
        } else {
            DB::commit();
        }
        $worker->wait();
        expect($worker->getExitCode())->toBe(0, $worker->getErrorOutput());
        expect($worker->getOutput())->toBe($childResult);
        expect(OperatorActivity::where('type', OperatorActivityType::StageOverdue)->count())->toBe($expectedCount);
        if (in_array($winner, ['review', 'detector before review', 'two reviews'], true)) {
            expect($stage->refresh()->status)->toBe(StageStatus::Completed);
            expect($project->refresh()->status)->toBe(ProjectStatus::AwaitingFinalPayment);
        }
        if ($winner === 'detector') {
            expect(OperatorActivity::where('type', OperatorActivityType::StageOverdue)->sole()->id)->toBe($firstEvent->id);
            $stage->refresh()->update(['status' => StageStatus::Completed, 'completed_at' => now()]);
            expect(app(RecordOverdueStages::class)->recordStage($stage->id))->toBeFalse();
            expect(OperatorActivity::where('type', OperatorActivityType::StageOverdue)->sole()->getRawOriginal())->toBe($firstEvent->getRawOriginal());
        }
    } finally {
        while (DB::connection('phase17_mysql')->transactionLevel() > 0) {
            DB::connection('phase17_mysql')->rollBack();
        }
        $worker?->stop();
        DB::purge('phase17_mysql');
        config(['database.default' => $originalConnection]);
    }
})->with([
    'detector first' => ['detector', 1, 'skipped'],
    'completion first' => ['completion', 0, 'skipped'],
    'detector rollback' => ['rollback', 1, 'recorded'],
    'stale deadline' => ['changed deadline', 0, 'skipped'],
    'review before detector' => ['review', 0, 'skipped'],
    'detector before review' => ['detector before review', 1, 'approved'],
    'database unique identity' => ['unique', 1, 'duplicate rejected'],
    'overlapping commands across batches' => ['batch', 201, 'Examined: 200; recorded: 0; skipped: 200.'],
    'concurrent reviewers' => ['two reviews', 0, 'approved'],
    'assignment before stale detector' => ['assignment', 0, 'skipped'],
    'detector before invalid reassignment' => ['detector before assignment', 1, 'assignment rejected'],
]);
