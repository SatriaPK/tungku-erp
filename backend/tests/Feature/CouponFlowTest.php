<?php

use App\Actions\Booking\ApplyBookingCoupon;
use App\Models\Coupon;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\RecordingSession;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\StudioSettingSeeder;

function couponData(array $overrides = []): array
{
    return array_merge(['code' => 'STUDIO10', 'name' => 'Promo Studio', 'type' => 'percent', 'value' => 10, 'minimum' => 1000, 'maximum' => 50000, 'start' => now()->subDay()->toDateString(), 'end' => now()->addDay()->toDateString(), 'limit' => 1, 'enabled' => true], $overrides);
}
test('manager creates shared coupon and clients cannot administer coupons', function () {
    $client = User::factory()->client()->create();
    $this->actingAs($client)->postJson('/api/v1/manager/coupons', couponData())->assertForbidden();
    $this->actingAs(User::factory()->manager()->create())->postJson('/api/v1/manager/coupons', couponData())->assertCreated();
    $package = Package::factory()->manualPrice(1000000)->create();
    $this->actingAs($client)->postJson('/api/v1/client/coupons/preview', ['coupon_code' => 'studio10', 'package_id' => $package->id])->assertOk()->assertJsonPath('data.discount', 50000)->assertJsonPath('data.total', 950000);
    $this->assertDatabaseHas('coupons', ['code' => 'STUDIO10', 'used' => 0]);
});
test('coupon rules reject expired disabled future minimum and exhausted coupons', function () {
    $client = User::factory()->client()->create();
    $package = Package::factory()->manualPrice(1000000)->create();
    foreach ([['enabled' => false], ['end' => now()->subDay()->toDateString()], ['start' => now()->addDay()->toDateString()], ['minimum' => 2000000], ['used' => 1]] as $overrides) {
        Coupon::query()->delete();
        $coupon = Coupon::create(couponData());
        $coupon->forceFill($overrides)->save();
        $this->actingAs($client)->postJson('/api/v1/client/coupons/preview', ['coupon_code' => 'STUDIO10', 'package_id' => $package->id])->assertUnprocessable()->assertJsonValidationErrors('coupon_code');
    }
});
test('booking writes discounted invoice and quota is enforced atomically', function () {
    $this->seed(StudioSettingSeeder::class);
    $client = User::factory()->client()->create();
    $room = Room::factory()->create();
    $package = Package::factory()->manualPrice(1000000)->create();
    Coupon::create(couponData());
    $payload = ['room_id' => $room->id, 'package_id' => $package->id, 'start_at' => now()->addDays(2)->setTime(10, 0)->toIso8601String(), 'end_at' => now()->addDays(2)->setTime(12, 0)->toIso8601String(), 'coupon_code' => 'STUDIO10'];
    $this->actingAs($client)->postJson('/api/v1/client/bookings/hold', $payload)->assertCreated();
    $invoice = Invoice::firstOrFail();
    expect($invoice->total)->toBe(950000)->and($invoice->discount_amount)->toBe(50000)->and((int) $invoice->items()->sum('total'))->toBe(950000);
    $payload['room_id'] = Room::factory()->create()->id;
    $this->postJson('/api/v1/client/bookings/hold', $payload)->assertUnprocessable();
    expect(Invoice::count())->toBe(1)->and(RecordingSession::count())->toBe(1)->and(Coupon::first()->used)->toBe(1);
});
test('fixed discount keeps invoice positive and validates duplicate code', function () {
    $this->actingAs(User::factory()->manager()->create());
    $this->postJson('/api/v1/manager/coupons', couponData(['type' => 'fixed', 'value' => 500000]))->assertCreated();
    $this->postJson('/api/v1/manager/coupons', couponData())->assertUnprocessable();
    $coupon = Coupon::first();
    expect(app(ApplyBookingCoupon::class)->discount($coupon, 100000))->toBe(99999);
});

test('a code cannot be reused by another client or package even with a legacy high limit', function () {
    $this->seed(StudioSettingSeeder::class);
    $client = User::factory()->client()->create();
    $coupon = Coupon::create(couponData(['limit' => 100]));
    $package = Package::factory()->manualPrice(1000000)->create();
    $payload = ['room_id' => Room::factory()->create()->id, 'package_id' => $package->id, 'coupon_code' => 'STUDIO10',
        'start_at' => now()->addDays(2)->setTime(10, 0)->toIso8601String(), 'end_at' => now()->addDays(2)->setTime(12, 0)->toIso8601String()];
    $this->actingAs($client)->postJson('/api/v1/client/bookings/hold', $payload)->assertCreated();
    $payload['room_id'] = Room::factory()->create()->id;
    $payload['package_id'] = Package::factory()->manualPrice(2000000)->create()->id;
    $this->postJson('/api/v1/client/bookings/hold', $payload)->assertUnprocessable()->assertJsonValidationErrors('coupon_code');
    $this->actingAs(User::factory()->client()->create());
    $this->postJson('/api/v1/client/coupons/preview', ['coupon_code' => 'studio10', 'package_id' => $payload['package_id']])->assertUnprocessable();
    $this->postJson('/api/v1/client/bookings/hold', $payload)->assertUnprocessable();
    expect(Invoice::count())->toBe(1)->and(RecordingSession::count())->toBe(1)->and($coupon->fresh()->used)->toBe(1);
    $this->actingAs(User::factory()->manager()->create());
    $this->putJson('/api/v1/manager/coupons/'.$coupon->id, couponData(['code' => 'RENAMED']))->assertUnprocessable();
    $this->putJson('/api/v1/manager/coupons/'.$coupon->id, couponData(['limit' => 100]))->assertOk()->assertJsonPath('data.limit', 1);
    $this->postJson('/api/v1/manager/coupons', couponData(['code' => 'ANOTHER', 'limit' => 100]))->assertCreated()->assertJsonPath('data.limit', 1);
});
