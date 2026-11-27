<?php

use App\Actions\Inventory\DeleteInventory;
use App\Actions\Inventory\DisposeInventory;
use App\Actions\Inventory\SaveInventory;
use App\Actions\InventoryCategory\SaveInventoryCategory;
use App\Models\Inventory;
use App\Models\InventoryCategory;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

test('inventory lifecycle serializes competing writers on MySQL', function (string $scenario) {
    if (getenv('PHASE16_MYSQL_TESTS') !== '1') {
        $this->markTestSkipped('Opt-in disposable MySQL at 127.0.0.1:33317 required.');
    }
    $observer = new PDO('mysql:host=127.0.0.1;port=33317', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $database = 'phase16_inventory_test_'.getmypid();
    $observer->exec('CREATE DATABASE IF NOT EXISTS '.$database);
    $originalConnection = config('database.default');
    config(['database.default' => 'phase16_mysql', 'database.connections.phase16_mysql' => [
        'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 33317, 'database' => $database,
        'username' => 'root', 'password' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '', 'strict' => true,
    ]]);

    $worker = null;
    try {
        expect(Artisan::call('migrate:fresh', ['--database' => 'phase16_mysql', '--force' => true]))->toBe(0);
        $manager = User::factory()->manager()->create();
        $category = InventoryCategory::create(['name' => 'Kamera', 'is_active' => true]);
        $data = ['code' => 'CAM-1', 'name' => 'Kamera', 'inventory_category_id' => $category->id,
            'purchase_date' => '2026-01-01', 'purchase_price' => 12000, 'residual_value' => 2000, 'useful_life_months' => 10];
        $inventory = app(SaveInventory::class)->handle($data, $manager);
        DB::beginTransaction();
        if ($scenario === 'category disabled before save') {
            InventoryCategory::whereKey($category->id)->lockForUpdate()->firstOrFail();
        } else {
            Inventory::whereKey($inventory->id)->lockForUpdate()->firstOrFail();
        }
        $code = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!preg_match('/^phase16_inventory_test_[0-9]+$/D', $argv[1])) { throw new RuntimeException('Unexpected test database'); }
config(['database.default'=>'phase16_mysql','database.connections.phase16_mysql'=>[
'driver'=>'mysql','host'=>'127.0.0.1','port'=>33317,'database'=>$argv[1],
'username'=>'root','password'=>'','charset'=>'utf8mb4','collation'=>'utf8mb4_unicode_ci','prefix'=>'','strict'=>true]]);
try {
    $inventory = App\Models\Inventory::findOrFail($argv[2]);
    if (in_array($argv[3], ['disposal before update', 'category disabled before save'])) {
        $data = json_decode($argv[4], true);
        $data['name'] = 'Changed';
        app(App\Actions\Inventory\SaveInventory::class)->handle($data, App\Models\User::findOrFail($argv[5]), $inventory);
    } elseif ($argv[3] === 'disposal before delete') {
        app(App\Actions\Inventory\DeleteInventory::class)->handle($inventory);
    } else {
        app(App\Actions\Inventory\DisposeInventory::class)->handle($inventory, ['type'=>'disposed', 'disposed_on'=>'2026-03-01']);
    }
    echo 'completed';
} catch (Illuminate\Validation\ValidationException|Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
    echo 'rejected';
}
PHP;
        $worker = new Process([PHP_BINARY, '-r', $code, $database, (string) $inventory->id, $scenario, json_encode($data), (string) $manager->id], base_path(), ['APP_ENV' => 'testing'], timeout: 40);
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
        expect($waiting)->toBeTrue('The competing connection must wait for the asset or category lock.');

        if ($scenario === 'category disabled before save') {
            app(SaveInventoryCategory::class)->handle(['name' => 'Kamera', 'is_active' => false], $category);
        } elseif ($scenario === 'delete before disposal') {
            app(DeleteInventory::class)->handle($inventory);
        } else {
            app(DisposeInventory::class)->handle($inventory, ['type' => 'sold', 'disposed_on' => '2026-02-01', 'disposal_value' => 5000]);
        }
        if ($scenario === 'rollback disposal') {
            DB::rollBack();
        } else {
            DB::commit();
        }
        $worker->wait();
        expect($worker->getExitCode())->toBe(0, $worker->getErrorOutput());
        expect($worker->getOutput())->toBe($scenario === 'rollback disposal' ? 'completed' : 'rejected');
        $saved = Inventory::withTrashed()->findOrFail($inventory->id);
        expect($saved->name)->toBe('Kamera');
        expect($saved->status->value)->toBe(match ($scenario) {
            'category disabled before save', 'delete before disposal' => 'active',
            'rollback disposal' => 'disposed',
            default => 'sold',
        });
        expect($saved->trashed())->toBe($scenario === 'delete before disposal');
        expect(DB::table('expenses')->count())->toBe(0);
        expect($saved->disposal_value)->toBe(in_array($scenario, ['category disabled before save', 'delete before disposal', 'rollback disposal'], true) ? null : 5000);
    } finally {
        while (DB::connection('phase16_mysql')->transactionLevel() > 0) {
            DB::connection('phase16_mysql')->rollBack();
        }
        $worker?->stop();
        DB::purge('phase16_mysql');
        config(['database.default' => $originalConnection]);
    }
})->with(['duplicate disposal', 'disposal before update', 'disposal before delete', 'delete before disposal', 'category disabled before save', 'rollback disposal']);
