<?php

use App\Actions\Invoice\EnsureFinalPaymentInvoice;
use App\Actions\Payment\ConfirmManualPayment;
use App\Actions\Payment\ConfirmPayment;
use App\Actions\Payment\InitiateFinalPayment;
use App\Enum\PaymentProvider;
use App\Models\Payment;
use App\Models\Project;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

test('final payment protects settlement from competing cash requests on MySQL', function (string $scenario) {
    if (getenv('PHASE13_MYSQL_TESTS') !== '1') {
        $this->markTestSkipped('Opt-in disposable MySQL at 127.0.0.1:33317 required.');
    }
    $observer = new PDO('mysql:host=127.0.0.1;port=33317', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $database = 'phase13_payments_test_'.getmypid();
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
        $project = Project::factory()->create(['status' => 'awaiting_final_payment', 'price' => 125000, 'po_amount' => 0, 'final_payment_amount' => 125000]);
        $payment = Payment::factory()->cash()->create(['project_id' => $project->id, 'type' => 'final', 'amount' => 125000, 'status' => 'pending', 'expires_at' => null]);
        if ($scenario === 'duplicate callback') {
            $payment->update(['provider' => PaymentProvider::Midtrans]);
        }
        app(EnsureFinalPaymentInvoice::class)->execute($payment);
        DB::beginTransaction();
        Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
        $code = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!preg_match('/^phase13_payments_test_[0-9]+$/D', $argv[1])) { throw new RuntimeException('Unexpected test database'); }
config(['database.default'=>'phase13_mysql','database.connections.phase13_mysql'=>[
'driver'=>'mysql','host'=>'127.0.0.1','port'=>33317,'database'=>$argv[1],
'username'=>'root','password'=>'','charset'=>'utf8mb4','collation'=>'utf8mb4_unicode_ci','prefix'=>'','strict'=>true]]);
try {
    $payment = App\Models\Payment::findOrFail($argv[2]);
    if ($argv[3] === 'duplicate callback') {
        app(App\Actions\Payment\ConfirmPayment::class)->execute($payment, 'provider-transaction');
    } elseif ($argv[3] === 'duplicate confirmation') {
        app(App\Actions\Payment\ConfirmManualPayment::class)->execute($payment, 'Second confirmation');
    } else {
        app(App\Actions\Payment\InitiateFinalPayment::class)->execute($payment, App\Enum\PaymentProvider::Cash);
    }
    echo 'completed';
} catch (InvalidArgumentException $exception) {
    echo 'rejected';
}
PHP;
        $worker = new Process([PHP_BINARY, '-r', $code, $database, (string) $payment->id, $scenario], base_path(), ['APP_ENV' => 'testing', 'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'database'], timeout: 40);
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
        expect($waiting)->toBeTrue('The competing connection must wait for the payment lock.');

        if ($scenario === 'duplicate callback') {
            app(ConfirmPayment::class)->execute($payment->fresh(), 'provider-transaction');
        } elseif ($scenario === 'duplicate initiation') {
            app(InitiateFinalPayment::class)->execute($payment->fresh(), PaymentProvider::Cash);
        } else {
            app(ConfirmManualPayment::class)->execute($payment->fresh(), 'First confirmation');
        }
        DB::commit();
        $worker->wait();
        expect($worker->getExitCode())->toBe(0, $worker->getErrorOutput());
        expect($worker->getOutput())->toBe($scenario === 'duplicate callback' ? 'completed' : 'rejected');
        expect($payment->fresh()->status->value)->toBe($scenario === 'duplicate initiation' ? 'pending_manual_confirmation' : 'paid');
        expect($project->fresh()->status->value)->toBe($scenario === 'duplicate initiation' ? 'awaiting_final_payment' : 'completed');
        expect($payment->fresh()->invoice->status->value)->toBe($scenario === 'duplicate initiation' ? 'issued' : 'paid');
        expect($project->payments()->count())->toBe(1);
        expect(DB::table('invoices')->count())->toBe(1);
    } finally {
        while (DB::connection('phase13_mysql')->transactionLevel() > 0) {
            DB::connection('phase13_mysql')->rollBack();
        }
        $worker?->stop();
        DB::purge('phase13_mysql');
        config(['database.default' => $originalConnection]);
    }
})->with(['confirmation before initiation', 'duplicate initiation', 'duplicate confirmation', 'duplicate callback']);
