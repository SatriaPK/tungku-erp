<?php

use App\Actions\Notification\DeliverNotification;
use App\Models\NotificationDelivery;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\Process\Process;

test('notification delivery serializes against another worker or bulk read on MySQL', function (string $scenario) {
    if (getenv('PHASE18_MYSQL_TESTS') !== '1') {
        $this->markTestSkipped('Opt-in disposable MySQL at 127.0.0.1:33317 required.');
    }
    $observer = new PDO('mysql:host=127.0.0.1;port=33317', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $database = 'phase18_notifications_test_'.getmypid();
    $observer->exec('CREATE DATABASE IF NOT EXISTS '.$database);
    $originalConnection = config('database.default');
    config(['database.default' => 'phase18_mysql', 'database.connections.phase18_mysql' => [
        'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 33317, 'database' => $database,
        'username' => 'root', 'password' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '', 'strict' => true,
    ]]);
    $worker = null;
    try {
        expect(Artisan::call('migrate:fresh', ['--database' => 'phase18_mysql', '--force' => true]))->toBe(0);
        expect($observer->query("SELECT ENGINE FROM information_schema.tables WHERE TABLE_SCHEMA='{$database}' AND TABLE_NAME='notification_deliveries'")->fetchColumn())->toBe('InnoDB');
        $user = User::factory()->client()->create();
        $delivery = NotificationDelivery::factory()->create(['recipient_id' => $user->id]);
        $old = $user->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'legacy', 'data' => []]);
        Sanctum::actingAs($user);
        DB::beginTransaction();
        User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
        $code = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!preg_match('/^phase18_notifications_test_[0-9]+$/D', $argv[1])) {
    throw new RuntimeException('Unexpected test database');
}
config(['database.default'=>'phase18_mysql','database.connections.phase18_mysql'=>[
'driver'=>'mysql','host'=>'127.0.0.1','port'=>33317,'database'=>$argv[1],
'username'=>'root','password'=>'','charset'=>'utf8mb4','collation'=>'utf8mb4_unicode_ci','prefix'=>'','strict'=>true]]);
if ($argv[4] === 'delivery before read') {
    $request = Illuminate\Http\Request::create('/api/v1/notifications/read-all', 'PATCH');
    $user = App\Models\User::findOrFail($argv[3]);
    $request->setUserResolver(fn () => $user);
    app(App\Http\Controllers\Api\V1\NotificationController::class)->readAll($request);
} else {
    app(App\Actions\Notification\DeliverNotification::class)->handle((int) $argv[2]);
}
echo 'completed';
PHP;
        $worker = new Process([PHP_BINARY, '-r', $code, $database, (string) $delivery->id, (string) $user->id, $scenario],
            base_path(), ['APP_ENV' => 'testing'], timeout: 30);
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
        expect($waiting)->toBeTrue('The competing connection must wait for the recipient lock.');
        if ($scenario === 'read before delivery') {
            $this->patchJson('/api/v1/notifications/read-all')->assertOk()->assertJsonPath('data.updated_count', 1);
        } else {
            app(DeliverNotification::class)->handle($delivery->id);
        }
        if ($scenario === 'rollback') {
            DB::rollBack();
        } else {
            DB::commit();
        }
        $worker->wait();
        expect($worker->getExitCode())->toBe(0, $worker->getErrorOutput());
        expect($worker->getOutput())->toBe('completed');
        expect($delivery->refresh()->status)->toBe('delivered');
        expect($delivery->attempts)->toBe(1);
        expect($user->notifications()->count())->toBe(2);
        $new = $user->notifications()->whereKey($delivery->notification_id)->sole();
        if ($scenario === 'delivery before read') {
            expect($new->read_at)->not->toBeNull();
            expect($old->fresh()->read_at)->not->toBeNull();
        } else {
            expect($new->read_at)->toBeNull();
            if ($scenario === 'read before delivery') {
                expect($old->fresh()->read_at)->not->toBeNull();
            }
        }
    } finally {
        while (DB::connection('phase18_mysql')->transactionLevel() > 0) {
            DB::connection('phase18_mysql')->rollBack();
        }
        $worker?->stop();
        DB::purge('phase18_mysql');
        config(['database.default' => $originalConnection]);
    }
})->with(['duplicate workers', 'read before delivery', 'delivery before read', 'rollback']);
