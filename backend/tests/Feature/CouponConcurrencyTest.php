<?php

use App\Actions\Booking\CouponPaymentLifecycle;
use App\Models\Coupon;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

test('two MySQL connections cannot reserve the final coupon quota', function () {
    if (getenv('COUPON_MYSQL_TESTS') !== '1') {
        $this->markTestSkipped('Requires opt-in local MySQL on port 3306.');
    }
    $database = 'coupon_concurrency_test_'.getmypid();
    $observer = new PDO('mysql:host=127.0.0.1;port=3306', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $observer->exec('CREATE DATABASE '.$database);
    $original = config('database.default');
    $connection = ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 3306, 'database' => $database, 'username' => 'root', 'password' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true];
    config(['database.default' => 'coupon_test', 'database.connections.coupon_test' => $connection]);
    $worker = null;
    try {
        expect(Artisan::call('migrate', ['--database' => 'coupon_test', '--force' => true]))->toBe(0);
        $coupon = Coupon::create(['code' => 'RACE', 'name' => 'Race test', 'kind' => 'general', 'type' => 'fixed', 'value' => 10000, 'minimum' => 0, 'maximum' => 0, 'start' => '2000-01-01', 'end' => '2099-12-31', 'limit' => 1, 'enabled' => true, 'quota_enabled' => true]);
        $payments = collect(range(1, 2))->map(function () use ($coupon) {
            $invoice = Invoice::factory()->create(['total' => 90000, 'metadata' => ['coupon' => ['coupon_id' => $coupon->id, 'subtotal' => 100000, 'discount' => 10000]]]);

            return Payment::factory()->create(['invoice_id' => $invoice->id, 'amount' => 90000]);
        });
        DB::beginTransaction();
        app(CouponPaymentLifecycle::class)->reserve($payments[0]);
        $code = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default' => 'coupon_test', 'database.connections.coupon_test' => json_decode($argv[1], true)]);
echo "ready\n";
try {
    app(App\Actions\Booking\CouponPaymentLifecycle::class)->reserve(App\Models\Payment::findOrFail($argv[2]));
    echo 'unexpected success';
} catch (Illuminate\Validation\ValidationException $exception) {
    echo 'quota rejected';
}
PHP;
        $worker = new Process([PHP_BINARY, '-r', $code, json_encode($connection), (string) $payments[1]->id], base_path(), ['APP_ENV' => 'testing'], timeout: 30);
        $worker->start();
        $deadline = microtime(true) + 10;
        do {
            $waiting = (int) $observer->query("SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE DB = '{$database}' AND INFO LIKE '%coupons%for update%' AND TIME >= 1")->fetchColumn() > 0;
            if ($waiting || ! $worker->isRunning()) {
                break;
            }
            usleep(20000);
        } while (microtime(true) < $deadline);
        expect($waiting)->toBeTrue('Second checkout must wait for the coupon row lock. '.$worker->getErrorOutput().$worker->getOutput());
        DB::commit();
        $worker->wait();
        expect($worker->getExitCode())->toBe(0, $worker->getErrorOutput());
        expect($worker->getOutput())->toContain('quota rejected');
        expect(DB::table('coupon_redemptions')->where('status', 'reserved')->count())->toBe(1);
        expect($coupon->fresh()->used)->toBe(0);
    } finally {
        while (DB::connection('coupon_test')->transactionLevel() > 0) {
            DB::connection('coupon_test')->rollBack();
        }
        $worker?->stop();
        DB::purge('coupon_test');
        config(['database.default' => $original]);
        $observer->exec('DROP DATABASE '.$database);
    }
});
