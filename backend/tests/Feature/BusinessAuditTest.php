<?php

use App\Actions\Audit\AuditActor;
use App\Actions\Audit\RecordBusinessChange;
use App\Actions\CRM\CreateCampaign;
use App\Actions\CRM\RevokeConsent;
use App\Actions\CRM\SendCampaign;
use App\Actions\Expense\DeleteExpense;
use App\Actions\Expense\SaveExpense;
use App\Actions\ExpenseCategory\SaveExpenseCategory;
use App\Actions\Finance\FinalizeMonthlyPayroll;
use App\Actions\Inventory\DisposeInventory;
use App\Actions\Inventory\SaveInventory;
use App\Actions\InventoryCategory\SaveInventoryCategory;
use App\Actions\Package\SavePackage;
use App\Actions\Pricing\UpdateBasePrices;
use App\Actions\Settings\UpdateStudioSetting;
use App\Jobs\HandlePaymentWebhook;
use App\Models\BasePrice;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\RecordingSession;
use App\Models\User;
use App\Models\WebhookLog;
use Database\Seeders\StudioSettingSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 16)->setTime(12, 0));
    config(['queue.default' => 'database']);
});

test('expense changes preserve safe before and after values and explicit actor after deletion', function () {
    $manager = User::factory()->manager()->create();
    $category = ExpenseCategory::query()->create(['name' => 'Rent', 'is_active' => true]);
    $data = ['expense_category_id' => $category->id, 'description' => 'private@example.test secret note', 'amount' => 10000, 'spent_on' => '2026-10-01'];
    $expense = app(SaveExpense::class)->handle($data, $manager);
    app(SaveExpense::class)->handle([...$data, 'amount' => 20000], $manager, $expense);
    app(SaveExpense::class)->handle([...$data, 'amount' => 20000], $manager, $expense);

    expect(Activity::query()->count())->toBe(2);
    $changed = Activity::query()->latest('id')->firstOrFail();
    expect($changed->properties['old']['amount'])->toBe(10000);
    expect($changed->properties['attributes']['amount'])->toBe(20000);
    expect($changed->properties['actor_id'])->toBe($manager->id);
    expect($changed->properties['occurred_at'])->toBe('2026-10-16T12:00:00+07:00');
    expect($changed->properties->toJson())->not->toContain('private@example.test', 'secret note', 'password');

    app(DeleteExpense::class)->handle($expense, AuditActor::user($manager));
    $managerId = $manager->id;
    $manager->delete();
    $deleted = Activity::query()->where('event', 'expense.deleted')->sole();
    expect($deleted->properties['subject_id'])->toBe($expense->id);
    expect($deleted->properties['actor_id'])->toBe($managerId);
    expect($deleted->properties['attributes'])->toBe([]);
    $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
});

test('audit storage failure rolls back the business mutation', function () {
    $manager = User::factory()->manager()->create();
    $category = ExpenseCategory::query()->create(['name' => 'Rent', 'is_active' => true]);
    DB::unprepared("CREATE TRIGGER reject_business_audit BEFORE INSERT ON activity_log BEGIN SELECT RAISE(ABORT, 'audit unavailable'); END");

    expect(fn () => app(SaveExpense::class)->handle([
        'expense_category_id' => $category->id, 'description' => 'Rent', 'amount' => 10000, 'spent_on' => '2026-10-01',
    ], $manager))->toThrow(QueryException::class);

    $this->assertDatabaseCount('expenses', 0);
    $this->assertDatabaseCount('activity_log', 0);
});

test('outer rollback removes nested audit and business changes together', function () {
    $manager = User::factory()->manager()->create();
    expect(fn () => DB::transaction(function () use ($manager) {
        app(SaveExpenseCategory::class)->handle(['name' => 'Rent'], actor: AuditActor::user($manager));
        throw new RuntimeException('abort outer workflow');
    }))->toThrow(RuntimeException::class, 'abort outer workflow');
    $this->assertDatabaseCount('expense_categories', 0);
    $this->assertDatabaseCount('activity_log', 0);
});

test('provider callbacks keep one paid audit and never use ambient authenticated actor', function () {
    Sanctum::actingAs(User::factory()->manager()->create());
    $payment = Payment::factory()->create(['provider' => 'midtrans', 'amount' => 10000]);
    $log = WebhookLog::query()->create(['gateway' => 'midtrans', 'event_type' => 'settlement', 'order_id' => $payment->order_id, 'payload' => ['token' => 'provider-secret'], 'status' => 'received']);
    $data = ['order_id' => $payment->order_id, 'amount' => 10000, 'status' => 'paid'];
    (new HandlePaymentWebhook($log, $data))->handle();
    (new HandlePaymentWebhook($log->fresh(), $data))->handle();

    $entry = Activity::query()->where('event', 'payment.paid')->sole();
    expect($entry->causer_id)->toBeNull();
    expect($entry->properties['actor_kind'])->toBe('provider');
    expect($entry->properties['actor_source'])->toBe('midtrans');
    expect($entry->properties->toJson())->not->toContain('provider-secret', 'token', 'raw_payload');
    expect($payment->fresh()->status->value)->toBe('paid');
});

test('expired hold scheduler records the system actor once and ignores confirmed sessions', function () {
    Sanctum::actingAs(User::factory()->manager()->create());
    $held = RecordingSession::factory()->held()->create(['held_until' => now()->subMinute()]);
    $confirmed = RecordingSession::factory()->confirmed()->create(['held_until' => now()->subMinute()]);
    $this->artisan('bookings:release-expired-holds')->assertSuccessful();
    $this->artisan('bookings:release-expired-holds')->assertSuccessful();

    $entry = Activity::query()->where('event', 'booking.expired')->sole();
    expect($entry->properties['actor_kind'])->toBe('system');
    expect($entry->properties['actor_source'])->toBe('bookings:release-expired-holds');
    expect($entry->causer_id)->toBeNull();
    expect($held->fresh()->status->value)->toBe('expired');
    expect($confirmed->fresh()->status->value)->toBe('confirmed');
});

test('event retry retains original audit actor and snapshot', function () {
    $category = ExpenseCategory::query()->create(['name' => 'Rent', 'is_active' => true]);
    $audit = app(RecordBusinessChange::class);
    DB::transaction(function () use ($audit, $category) {
        $audit->handle($category, 'category.created', [], AuditActor::system('original'), 'category:'.$category->id.':created');
        $category->update(['is_active' => false]);
        $audit->handle($category, 'category.created', [], AuditActor::system('retry'), 'category:'.$category->id.':created');
    });
    $entry = Activity::query()->sole();
    expect($entry->properties['actor_source'])->toBe('original');
    expect($entry->properties['attributes']['is_active'])->toBeTrue();
});

test('pricing and studio settings record actual changes without private descriptions', function () {
    $this->seed(StudioSettingSeeder::class);
    $manager = User::factory()->manager()->create();
    BasePrice::query()->create(['stage' => 'editing', 'unit' => 'track', 'price' => 10000]);
    $package = app(SavePackage::class)->handle(['name' => 'Package', 'description' => 'private package note', 'track_count' => 1, 'recording_hours' => 0, 'include_recording' => false, 'include_editing' => true, 'include_mixing' => false, 'include_mastering' => false], $manager);
    app(UpdateBasePrices::class)->handle($manager, ['editing' => 20000]);
    app(UpdateStudioSetting::class)->handle('po_percentage', 60, $manager);

    expect($package->fresh()->auto_price)->toBe(20000);
    expect(Activity::query()->where('event', 'package.price_recalculated')->sole()->properties['old']['auto_price'])->toBe(10000);
    expect(Activity::query()->where('event', 'setting.updated')->sole()->properties['attributes']['value'])->toBe('60');
    expect(Activity::all()->toJson())->not->toContain('private package note');
});

test('inventory audit records acquisition and disposal amounts', function () {
    $manager = User::factory()->manager()->create();
    $actor = AuditActor::user($manager);
    $category = app(SaveInventoryCategory::class)->handle(['name' => 'Equipment'], actor: $actor);
    $inventory = app(SaveInventory::class)->handle(['code' => 'MIC-1', 'name' => 'Microphone', 'inventory_category_id' => $category->id, 'purchase_date' => '2026-01-01', 'purchase_price' => 120000, 'useful_life_months' => 12, 'notes' => 'secret asset note'], $manager);
    app(DisposeInventory::class)->handle($inventory, ['type' => 'sold', 'disposed_on' => '2026-10-16', 'disposal_value' => 50000], $actor);
    $entry = Activity::query()->where('event', 'inventory.disposed')->sole();
    expect($entry->properties['old']['status'])->toBe('active');
    expect($entry->properties['attributes']['disposal_value'])->toBe(50000);
    expect(Activity::all()->toJson())->not->toContain('secret asset note');
});

test('payroll finalization and retries preserve the original financial audit', function () {
    $manager = User::factory()->manager()->create();
    app(FinalizeMonthlyPayroll::class)->handle(2026, 9, $manager);
    app(FinalizeMonthlyPayroll::class)->handle(2026, 9, User::factory()->manager()->create());
    $entry = Activity::query()->where('event', 'payroll.finalized')->sole();
    expect($entry->properties['actor_id'])->toBe($manager->id);
    expect($entry->properties['attributes']['status'])->toBe('finalized');
    expect($entry->properties['attributes']['total_revenue'])->toBe(0);
});

test('CRM audit excludes recipients and message contents and records consent revocation once', function () {
    $manager = User::factory()->manager()->create();
    $client = User::factory()->client()->create(['crm_consent_at' => now()]);
    $campaign = app(CreateCampaign::class)->handle($manager, ['name' => 'Campaign', 'message' => 'private campaign content', 'channels' => ['email'], 'client_ids' => [$client->id]]);
    app(SendCampaign::class)->handle($campaign, AuditActor::user($manager));
    app(RevokeConsent::class)->handle($client);
    app(RevokeConsent::class)->handle($client);
    expect(Activity::query()->where('event', 'campaign.created')->sole()->properties['attributes']['recipient_count'])->toBe(1);
    expect(Activity::query()->where('event', 'crm.consent_revoked')->count())->toBe(1);
    expect(Activity::all()->toJson())->not->toContain('private campaign content', $client->email);
});
