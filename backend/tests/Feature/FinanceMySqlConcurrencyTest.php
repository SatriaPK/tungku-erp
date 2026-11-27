<?php

use App\Actions\Expense\SaveExpense;
use App\Actions\Finance\FinalizeMonthlyPayroll;
use App\Actions\Finance\SavePayrollAllocations;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\Payroll;
use App\Models\Project;
use App\Models\ProjectTrack;
use App\Models\TrackStageProgress;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

test('payroll serializes competing writers on MySQL', function (string $scenario) {
    if (getenv('PHASE14_MYSQL_TESTS') !== '1') {
        $this->markTestSkipped('Opt-in disposable MySQL at 127.0.0.1:33317 required.');
    }
    $this->travelTo(now()->setDate(2026, 10, 24));
    $observer = new PDO('mysql:host=127.0.0.1;port=33317', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $database = 'phase14_finance_test_'.getmypid();
    $observer->exec('CREATE DATABASE IF NOT EXISTS '.$database);
    $originalConnection = config('database.default');
    config(['database.default' => 'phase14_mysql', 'database.connections.phase14_mysql' => [
        'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 33317, 'database' => $database,
        'username' => 'root', 'password' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '', 'strict' => true,
    ]]);

    $worker = null;
    try {
        expect(Artisan::call('migrate:fresh', ['--database' => 'phase14_mysql', '--force' => true]))->toBe(0);
        $manager = User::factory()->manager()->create();
        $category = ExpenseCategory::create(['name' => 'Sewa', 'is_active' => true]);
        $operator = User::factory()->operator()->create();
        $project = Project::factory()->create(['status' => 'completed', 'completed_at' => '2026-09-16 12:00:00', 'package_snapshot' => ['stages' => ['editing']]]);
        $stage = $project->stages()->create(['stage' => 'editing', 'sequence' => 1, 'operator_id' => $operator->id, 'status' => 'completed']);
        $track = ProjectTrack::create(['project_id' => $project->id, 'title' => 'Track', 'position' => 1]);
        TrackStageProgress::create(['project_stage_id' => $stage->id, 'project_track_id' => $track->id, 'status' => 'approved', 'approved_at' => '2026-09-15 12:00:00']);
        Payment::factory()->paid()->create(['project_id' => $project->id, 'amount' => 1001, 'paid_at' => '2026-09-15 12:00:00']);
        $payroll = app(SavePayrollAllocations::class)->handle(2026, 9, [['operator_id' => $operator->id, 'percentage' => '40.00']], '50.00');
        $data = ['expense_category_id' => $category->id, 'description' => 'Sewa', 'amount' => 200, 'spent_on' => '2026-09-15'];
        DB::beginTransaction();
        Payroll::query()->where('period_year', 2026)->where('period_month', 9)->lockForUpdate()->firstOrFail();
        $code = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!preg_match('/^phase14_finance_test_[0-9]+$/D', $argv[1])) { throw new RuntimeException('Unexpected test database'); }
config(['database.default'=>'phase14_mysql','database.connections.phase14_mysql'=>[
'driver'=>'mysql','host'=>'127.0.0.1','port'=>33317,'database'=>$argv[1],
'username'=>'root','password'=>'','charset'=>'utf8mb4','collation'=>'utf8mb4_unicode_ci','prefix'=>'','strict'=>true]]);
Illuminate\Support\Carbon::setTestNow('2026-10-24 12:00:00');
try {
    $manager = App\Models\User::findOrFail($argv[2]);
    if ($argv[3] === 'finalization before expense') {
        app(App\Actions\Expense\SaveExpense::class)->handle(json_decode($argv[4], true), $manager);
    } elseif ($argv[3] === 'finalization before allocation' || $argv[3] === 'allocation before allocation') {
        app(App\Actions\Finance\SavePayrollAllocations::class)->handle(2026, 9, [['operator_id' => (int) $argv[5], 'percentage' => '20.00']], '75.00');
    } else {
        app(App\Actions\Finance\FinalizeMonthlyPayroll::class)->handle(2026, 9, $manager);
    }
    echo 'completed';
} catch (Illuminate\Validation\ValidationException $exception) {
    echo 'rejected';
}
PHP;
        $worker = new Process([PHP_BINARY, '-r', $code, $database, (string) $manager->id, $scenario, json_encode($data), (string) $operator->id], base_path(), ['APP_ENV' => 'testing'], timeout: 40);
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
        expect($waiting)->toBeTrue('The competing connection must wait for the period lock.');

        if ($scenario === 'expense before finalization') {
            app(SaveExpense::class)->handle($data, $manager);
        } elseif (in_array($scenario, ['allocation before finalization', 'allocation before allocation'], true)) {
            app(SavePayrollAllocations::class)->handle(2026, 9, [['operator_id' => $operator->id, 'percentage' => '30.00']], '60.00');
        } else {
            app(FinalizeMonthlyPayroll::class)->handle(2026, 9, $manager);
        }
        DB::commit();
        $worker->wait();
        expect($worker->getExitCode())->toBe(0, $worker->getErrorOutput());
        expect($worker->getOutput())->toBe(in_array($scenario, ['finalization before expense', 'finalization before allocation'], true) ? 'rejected' : 'completed');
        $payroll->refresh();
        expect($payroll->items()->count())->toBe(1);
        expect($payroll->items()->sole()->amount)->toBe(match ($scenario) {
            'expense before finalization' => 320,
            'allocation before finalization' => 300,
            'allocation before allocation' => 0,
            default => 400,
        });
        expect(Payroll::count())->toBe(1);
        expect($payroll->getRawOriginal('status'))->toBe($scenario === 'allocation before allocation' ? 'draft' : 'finalized');
        expect($payroll->total_expense)->toBe($scenario === 'expense before finalization' ? 200 : 0);
        expect(Expense::count())->toBe($scenario === 'expense before finalization' ? 1 : 0);
        expect((float) $payroll->studio_profit_percentage)->toBe(match ($scenario) {
            'allocation before finalization' => 60.0,
            'allocation before allocation' => 75.0,
            default => 50.0,
        });
    } finally {
        while (DB::connection('phase14_mysql')->transactionLevel() > 0) {
            DB::connection('phase14_mysql')->rollBack();
        }
        $worker?->stop();
        DB::purge('phase14_mysql');
        config(['database.default' => $originalConnection]);
    }
})->with(['expense before finalization', 'finalization before expense', 'allocation before finalization', 'finalization before allocation', 'allocation before allocation', 'duplicate finalization']);
