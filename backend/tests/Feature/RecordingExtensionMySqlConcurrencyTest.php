<?php

use App\Actions\Booking\RequestRecordingExtension;
use App\Models\BasePrice;
use App\Models\Project;
use App\Models\RecordingSession;
use App\Models\Room;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\StudioSettingSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

test('competing recording extensions cannot double book a room on MySQL', function (bool $rollback) {
    if (getenv('PHASE13_MYSQL_TESTS') !== '1') {
        $this->markTestSkipped('Opt-in disposable MySQL at 127.0.0.1:33317 required.');
    }
    $this->travelTo(now()->setDate(2026, 10, 24)->setTime(9, 0));
    $observer = new PDO('mysql:host=127.0.0.1;port=33317', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $database = 'phase13_extension_test_'.getmypid();
    $observer->exec('CREATE DATABASE IF NOT EXISTS '.$database);
    $originalConnection = config('database.default');
    config(['database.default' => 'phase13_mysql', 'database.connections.phase13_mysql' => [
        'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 33317, 'database' => $database,
        'username' => 'root', 'password' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '', 'strict' => true,
    ]]);

    $worker = null;
    try {
        expect(Artisan::call('migrate:fresh', ['--database' => 'phase13_mysql', '--force' => true]))->toBe(0);
        $this->seed(StudioSettingSeeder::class);
        $operator = User::factory()->operator()->create();
        $project = Project::factory()->create(['status' => 'in_progress']);
        $stage = $project->stages()->create(['stage' => 'recording', 'sequence' => 1, 'operator_id' => $operator->id, 'status' => 'in_progress']);
        $room = Room::factory()->create();
        BasePrice::create(['stage' => 'recording', 'unit' => 'hour', 'price' => 150000]);
        DB::beginTransaction();
        Room::whereKey($room->id)->lockForUpdate()->firstOrFail();
        $code = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!preg_match('/^phase13_extension_test_[0-9]+$/D', $argv[1])) { throw new RuntimeException('Unexpected test database'); }
config(['database.default'=>'phase13_mysql','database.connections.phase13_mysql'=>[
'driver'=>'mysql','host'=>'127.0.0.1','port'=>33317,'database'=>$argv[1],
'username'=>'root','password'=>'','charset'=>'utf8mb4','collation'=>'utf8mb4_unicode_ci','prefix'=>'','strict'=>true]]);
Illuminate\Support\Carbon::setTestNow('2026-10-24 09:00:00');
try {
    app(App\Actions\Booking\RequestRecordingExtension::class)->handle(
        App\Models\ProjectStage::findOrFail($argv[2]), App\Models\User::findOrFail($argv[3]), (int) $argv[4],
        Carbon\CarbonImmutable::parse('2026-10-25 10:00:00'), Carbon\CarbonImmutable::parse('2026-10-25 11:01:00'));
    echo 'completed';
} catch (Illuminate\Validation\ValidationException $exception) {
    echo 'rejected';
}
PHP;
        $worker = new Process([PHP_BINARY, '-r', $code, $database, (string) $stage->id, (string) $operator->id, (string) $room->id], base_path(), ['APP_ENV' => 'testing', 'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'database'], timeout: 40);
        $worker->start();
        $deadline = microtime(true) + 10;
        do {
            $waiting = (int) $observer->query("SELECT COUNT(*) FROM performance_schema.data_lock_waits w JOIN performance_schema.data_locks l ON l.ENGINE_LOCK_ID=w.REQUESTING_ENGINE_LOCK_ID WHERE l.OBJECT_SCHEMA='{$database}'")->fetchColumn() > 0;
            if ($waiting) {
                break;
            }
            if (! $worker->isRunning()) {
                throw new RuntimeException($worker->getErrorOutput().$worker->getOutput());
            }
            usleep(10000);
        } while (microtime(true) < $deadline);
        expect($waiting)->toBeTrue('The competing connection must wait for the room lock.');

        app(RequestRecordingExtension::class)->handle($stage, $operator, $room->id,
            CarbonImmutable::parse('2026-10-25 10:00:00'), CarbonImmutable::parse('2026-10-25 11:01:00'));
        if ($rollback) {
            DB::rollBack();
        } else {
            DB::commit();
        }
        $worker->wait();
        expect($worker->getExitCode())->toBe(0, $worker->getErrorOutput());
        expect($worker->getOutput())->toBe($rollback ? 'completed' : 'rejected');
        expect(RecordingSession::count())->toBe(1);
        expect(RecordingSession::sole()->extra_fee)->toBe(300000);
        expect(RecordingSession::sole()->status->value)->toBe('confirmed');
    } finally {
        while (DB::connection('phase13_mysql')->transactionLevel() > 0) {
            DB::connection('phase13_mysql')->rollBack();
        }
        $worker?->stop();
        DB::purge('phase13_mysql');
        config(['database.default' => $originalConnection]);
    }
})->with(['commit' => false, 'rollback' => true]);
