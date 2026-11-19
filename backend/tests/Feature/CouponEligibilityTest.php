<?php

use App\Actions\Booking\CouponEligibility;
use App\Models\Coupon;
use Illuminate\Validation\ValidationException;

function campaignRule(array $overrides = []): Coupon
{
    return new Coupon(array_merge(['code' => 'TEST', 'name' => 'Test', 'kind' => 'general', 'enabled' => true, 'type' => 'percent', 'value' => 10, 'minimum' => 100000, 'maximum' => 5000, 'minimum_enabled' => false, 'maximum_enabled' => false, 'validity_enabled' => false, 'quota_enabled' => false, 'package_mode' => 'all'], $overrides));
}

test('optional minimum and maximum can be disabled', function () {
    $c = campaignRule();
    expect(app(CouponEligibility::class)->discount($c, $c, 50000, 1, 1))->toBe(5000);
    expect(app(CouponEligibility::class)->discount($c, $c, 200000, 1, 1))->toBe(20000);
    $c->maximum_enabled = true;
    expect(app(CouponEligibility::class)->discount($c, $c, 200000, 1, 1))->toBe(5000);
});

test('whitelist excludes unspecified custom quotations', function () {
    $c = campaignRule(['package_mode' => 'whitelist', 'package_ids' => [1]]);
    app(CouponEligibility::class)->discount($c, $c, 200000, 1, null);
})->throws(ValidationException::class);

test('disabled parent blocks enabled child', function () {
    $parent = campaignRule(['kind' => 'parent', 'enabled' => false]);
    $child = campaignRule(['kind' => 'child']);
    app(CouponEligibility::class)->discount($child, $parent, 200000, 1, 1);
})->throws(ValidationException::class);

test('blacklist rejects excluded package', function () {
    $c = campaignRule(['package_mode' => 'blacklist', 'package_ids' => [2]]);
    app(CouponEligibility::class)->discount($c, $c, 200000, 1, 2);
})->throws(ValidationException::class);
