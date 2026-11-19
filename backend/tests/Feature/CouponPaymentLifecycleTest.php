<?php

use App\Actions\Booking\ApplyBookingCoupon;
use App\Actions\Booking\CouponPaymentLifecycle;
use App\Actions\Payment\CancelPayment;
use App\Enum\PaymentStatus;
use App\Models\Coupon;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Validation\ValidationException;

function paymentForCoupon(Coupon $coupon): Payment
{
    $invoice = Invoice::factory()->create(['total' => 90000, 'metadata' => ['coupon' => ['coupon_id' => $coupon->id, 'subtotal' => 100000, 'discount' => 10000]]]);

    return Payment::factory()->create(['invoice_id' => $invoice->id, 'amount' => 90000]);
}

function generalCoupon(): Coupon
{
    return Coupon::create(['code' => 'GENERAL', 'name' => 'General', 'kind' => 'general', 'type' => 'fixed', 'value' => 10000, 'minimum' => 0, 'maximum' => 0, 'start' => '2000-01-01', 'end' => '2099-12-31', 'limit' => 1, 'enabled' => true, 'quota_enabled' => true]);
}

test('reservation does not consume usage and release returns capacity', function () {
    $coupon = generalCoupon();
    $first = paymentForCoupon($coupon);
    $second = paymentForCoupon($coupon);
    $service = app(CouponPaymentLifecycle::class);
    $service->reserve($first);
    expect($coupon->fresh()->used)->toBe(0);
    try {
        $service->reserve($second);
        $this->fail('Quota should be reserved');
    } catch (ValidationException) {
    }
    $service->release($first);
    $service->reserve($second);
    expect($coupon->fresh()->used)->toBe(0);
    $second->update(['status' => PaymentStatus::Paid]);
    $service->redeem($second);
    $service->redeem($second);
    expect($coupon->fresh()->used)->toBe(1);
    $this->assertDatabaseHas('coupon_redemptions', ['payment_id' => $first->id, 'status' => 'released']);
    $this->assertDatabaseHas('coupon_redemptions', ['payment_id' => $second->id, 'status' => 'redeemed']);
});

test('general coupon allows different clients but rejects same client after payment', function () {
    $coupon = generalCoupon();
    $coupon->update(['quota_enabled' => false]);
    $service = app(CouponPaymentLifecycle::class);
    $first = paymentForCoupon($coupon);
    $service->reserve($first);
    $first->update(['status' => PaymentStatus::Paid]);
    $service->redeem($first);
    $other = paymentForCoupon($coupon);
    $service->reserve($other);
    $duplicate = paymentForCoupon($coupon);
    $duplicate->invoice->update(['client_id' => $first->invoice->client_id]);
    $service->reserve($duplicate);
})->throws(ValidationException::class);

test('payment retry preserves released attempt in coupon history', function () {
    $coupon = generalCoupon();
    $first = paymentForCoupon($coupon);
    $service = app(CouponPaymentLifecycle::class);
    $service->reserve($first);
    $service->release($first);
    $retry = Payment::factory()->create(['invoice_id' => $first->invoice_id, 'amount' => 90000]);
    $service->reserve($retry);
    $retry->update(['status' => PaymentStatus::Paid]);
    $service->redeem($retry);
    $this->assertDatabaseHas('coupon_redemptions', ['payment_id' => $first->id, 'status' => 'released']);
    $this->assertDatabaseHas('coupon_redemptions', ['payment_id' => $retry->id, 'status' => 'redeemed']);
    expect($coupon->fresh()->used)->toBe(1);
});

test('manager generates children and parent rules are inherited immediately', function () {
    $manager = User::factory()->manager()->create();
    $parent = generalCoupon();
    $parent->update(['kind' => 'parent']);
    $this->actingAs($manager)->postJson('/api/v1/manager/coupons/'.$parent->id.'/generate', ['count' => 3])->assertCreated()->assertJsonCount(3, 'data');
    $children = Coupon::where('parent_id', $parent->id)->get();
    expect($children->pluck('code')->unique())->toHaveCount(3);
    $child = $children->first();
    $parent->update(['value' => 20000]);
    expect(app(ApplyBookingCoupon::class)->discount($child, 100000, 1))->toBe(20000);
    $parent->update(['enabled' => false]);
    $response = $this->getJson('/api/v1/manager/coupons')->assertOk();
    expect(collect($response->json('data'))->firstWhere('id', $child->id)['effective_enabled'])->toBeFalse();
    $this->putJson('/api/v1/manager/coupons/'.$child->id, ['enabled' => false])->assertOk();
    $parent->update(['enabled' => true]);
    expect($child->fresh()->enabled)->toBeFalse();
});

test('unique child is consumed only by successful payment and cannot be reused', function () {
    $parent = generalCoupon();
    $parent->update(['kind' => 'parent']);
    $child = $parent->replicate();
    $child->fill(['code' => 'CHILD-TEST', 'kind' => 'child', 'parent_id' => $parent->id])->save();
    $payment = paymentForCoupon($child);
    $service = app(CouponPaymentLifecycle::class);
    $service->reserve($payment);
    expect($child->fresh()->used)->toBe(0);
    $payment->update(['status' => PaymentStatus::Paid]);
    $service->redeem($payment);
    expect($child->fresh()->used)->toBe(1)->and($parent->fresh()->used)->toBe(1);
    $service->reserve(paymentForCoupon($child));
})->throws(ValidationException::class);

test('late cancellation cannot overwrite a concurrently paid payment', function () {
    $payment = Payment::factory()->create();
    Payment::whereKey($payment->id)->update(['status' => 'paid']);
    try {
        app(CancelPayment::class)->execute($payment, 'Stale request');
        $this->fail('Stale cancellation must be rejected');
    } catch (InvalidArgumentException) {
        expect($payment->fresh()->status)->toBe(PaymentStatus::Paid);
    }
});
