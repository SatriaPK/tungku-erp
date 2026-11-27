<?php

use App\Actions\Notification\DeliverNotification;
use App\Actions\Notification\RecordNotification;
use App\Models\NotificationDelivery;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentConfirmedNotification;
use App\Notifications\PaymentCreatedNotification;
use App\Notifications\PaymentFailedNotification;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    expect(config('database.default'))->toBe('sqlite');
    expect(config('database.connections.sqlite.database'))->toBe(':memory:');
    $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
    $this->freezeTime();
    DB::connection()->setTransactionManager(new DatabaseTransactionsManager);
});

afterEach(function () {
    RefreshDatabaseState::$migrated = false;
});

test('nested commits deliver only after the real outer commit and retries retain one database notification', function () {
    $user = User::factory()->client()->create();
    $payment = Payment::factory()->create(['status' => 'failed']);
    $notice = new PaymentFailedNotification($payment);

    DB::beginTransaction();
    DB::transaction(fn () => app(RecordNotification::class)->handle($user, $notice));
    $this->assertDatabaseCount('notification_deliveries', 1);
    $this->assertDatabaseCount('notifications', 0);
    DB::commit();

    $this->assertDatabaseCount('notifications', 1);
    app(RecordNotification::class)->handle($user, $notice);
    $delivery = NotificationDelivery::query()->sole();
    app(DeliverNotification::class)->handle($delivery->id);
    expect($delivery->fresh()->status)->toBe('delivered');
    $this->assertDatabaseCount('notification_deliveries', 1);
    $this->assertDatabaseCount('notifications', 1);
    expect($user->notifications()->sole()->data)->toBe($notice->toArray($user));
});

test('outer and nested rollbacks leave neither intents nor notifications', function (bool $nested) {
    $user = User::factory()->client()->create();
    $payment = Payment::factory()->create(['status' => 'failed']);
    DB::beginTransaction();
    if ($nested) {
        DB::beginTransaction();
    }
    app(RecordNotification::class)->handle($user, new PaymentFailedNotification($payment));
    DB::rollBack();
    if ($nested) {
        DB::commit();
    }
    $this->assertDatabaseCount('notification_deliveries', 0);
    $this->assertDatabaseCount('notifications', 0);
})->with([false, true]);

test('a rolled back attempt can be retried with the same event identity', function () {
    $user = User::factory()->client()->create();
    $payment = Payment::factory()->create(['status' => 'failed']);
    $notice = new PaymentFailedNotification($payment);
    DB::beginTransaction();
    app(RecordNotification::class)->handle($user, $notice);
    DB::rollBack();
    DB::transaction(fn () => app(RecordNotification::class)->handle($user, $notice));
    $this->assertDatabaseCount('notification_deliveries', 1);
    $this->assertDatabaseCount('notifications', 1);
});

test('failed enqueue is recovered from persisted intent', function () {
    $user = User::factory()->client()->create();
    $payment = Payment::factory()->create(['status' => 'failed']);
    config(['queue.default' => 'unavailable-connection']);
    app(RecordNotification::class)->handle($user, new PaymentFailedNotification($payment));
    expect(NotificationDelivery::query()->sole()->status)->toBe('pending');
    $this->assertDatabaseCount('notifications', 0);
    config(['queue.default' => 'sync']);
    $this->artisan('notifications:recover')->assertSuccessful();
    $this->artisan('notifications:recover')->assertSuccessful();
    $this->assertDatabaseCount('notifications', 1);
});

test('inactive and missing recipients are suppressed permanently', function (string $state) {
    $user = User::factory()->client()->create();
    $delivery = NotificationDelivery::factory()->create(['recipient_id' => $user->id]);
    if ($state === 'missing') {
        $user->delete();
    } else {
        $user->forceFill(['is_active' => false])->save();
    }
    app(DeliverNotification::class)->handle($delivery->id);
    expect($delivery->fresh()->status)->toBe('suppressed');
    if ($state === 'inactive') {
        $user->forceFill(['is_active' => true])->save();
        app(DeliverNotification::class)->handle($delivery->id);
    }
    $this->assertDatabaseCount('notifications', 0);
    $this->artisan('notifications:recover', ['--retry' => $delivery->id])->assertFailed();
})->with(['inactive', 'missing']);

test('mail failure does not remove inbox notification and retries are bounded with explicit recovery', function () {
    $user = User::factory()->client()->create();
    $payment = Payment::factory()->paid()->create();
    config(['mail.default' => 'unavailable-mailer']);
    app(RecordNotification::class)->handle($user, new PaymentConfirmedNotification($payment));
    $this->assertDatabaseCount('notifications', 1);
    $delivery = NotificationDelivery::query()->where('channel', 'mail')->sole();
    expect($delivery->status)->toBe('pending')->and($delivery->attempts)->toBe(1);
    app(DeliverNotification::class)->handle($delivery->id);
    expect($delivery->fresh()->attempts)->toBe(1);
    $this->travel(60)->seconds();
    app(DeliverNotification::class)->handle($delivery->id);
    expect($delivery->fresh()->attempts)->toBe(2);
    $this->travel(300)->seconds();
    expect(fn () => app(DeliverNotification::class)->handle($delivery->id))->toThrow(RuntimeException::class, 'exhausted');
    expect($delivery->fresh()->status)->toBe('failed');
    config(['mail.default' => 'array']);
    $this->artisan('notifications:recover', ['--retry' => $delivery->id])->assertSuccessful();
    expect($delivery->fresh()->status)->toBe('delivered');
    $this->assertDatabaseCount('notifications', 1);
    $this->assertDatabaseCount('notification_deliveries', 2);
});

test('an active lease prevents duplicate work and an expired lease is recovered', function () {
    $delivery = NotificationDelivery::factory()->create(['status' => 'processing', 'lease_until' => now()->addMinutes(5), 'attempts' => 1]);
    app(DeliverNotification::class)->handle($delivery->id);
    $this->assertDatabaseCount('notifications', 0);
    $this->travel(5)->minutes();
    $this->artisan('notifications:recover')->assertSuccessful();
    expect($delivery->fresh()->status)->toBe('delivered');
    $this->assertDatabaseCount('notifications', 1);
});

test('mail uses the committed safe snapshot and delivery never replaces legacy notifications', function () {
    $user = User::factory()->client()->create();
    $payment = Payment::factory()->paid()->create(['amount' => 12345, 'snap_token' => 'private-token']);
    $legacy = $user->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'legacy', 'data' => ['type' => 'legacy']]);
    config(['queue.default' => 'database']);
    app(RecordNotification::class)->handle($user, new PaymentConfirmedNotification($payment));
    $payment->update(['amount' => 999]);
    $delivery = NotificationDelivery::query()->where('channel', 'mail')->sole();
    expect(json_encode($delivery->getAttributes()))->not->toContain('private-token');
    Notification::fake();
    app(DeliverNotification::class)->handle($delivery->id);
    Notification::assertSentTo($user, PaymentConfirmedNotification::class, fn ($notice) => $notice->payment->amount === 12345);
    expect($legacy->fresh()->data)->toBe(['type' => 'legacy']);
});

test('database transaction retry commits only the successful attempt', function () {
    $user = User::factory()->client()->create();
    $payment = Payment::factory()->create(['status' => 'failed']);
    $attempts = 0;
    DB::transaction(function () use ($user, $payment, &$attempts): void {
        app(RecordNotification::class)->handle($user, new PaymentFailedNotification($payment));
        if (++$attempts === 1) {
            throw new PDOException('deadlock detected');
        }
    }, 3);
    expect($attempts)->toBe(2);
    $this->assertDatabaseCount('notification_deliveries', 1);
    $this->assertDatabaseCount('notifications', 1);
});

test('retries preserve read state and deleting the display row does not replay its event', function () {
    $user = User::factory()->client()->create();
    $payment = Payment::factory()->create(['status' => 'failed']);
    $notice = new PaymentFailedNotification($payment);
    app(RecordNotification::class)->handle($user, $notice);
    $row = $user->notifications()->sole();
    $row->markAsRead();
    app(RecordNotification::class)->handle($user, $notice);
    expect($row->fresh()->read_at)->not->toBeNull();
    $row->delete();
    app(RecordNotification::class)->handle($user, $notice);
    $this->artisan('notifications:recover')->assertSuccessful();
    $this->assertDatabaseCount('notifications', 0);
    $this->assertDatabaseCount('notification_deliveries', 1);
});

test('payment mail retains the local deadline snapshot without storing gateway secrets', function () {
    $user = User::factory()->client()->create();
    $payment = Payment::factory()->create(['expires_at' => now()->setTime(15, 30), 'payment_url' => 'https://example.com/pay/private']);
    config(['queue.default' => 'database']);
    app(RecordNotification::class)->handle($user, new PaymentCreatedNotification($payment));
    $delivery = NotificationDelivery::query()->where('channel', 'mail')->sole();
    expect(json_encode($delivery->getAttributes()))->not->toContain('https://example.com/pay/private');
    Notification::fake();
    app(DeliverNotification::class)->handle($delivery->id);
    Notification::assertSentTo($user, PaymentCreatedNotification::class,
        fn ($notice) => $notice->payment->expires_at->format('H:i') === '15:30');
});
