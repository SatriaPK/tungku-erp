<?php

use App\Services\Payment\ManualGateway;
use App\Services\Payment\MidtransGateway;
use App\Services\Payment\PaymentGatewayFactory;
use App\Services\Payment\XenditGateway;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    // Clear any fake instance set by other tests
    PaymentGatewayFactory::clearFake();
});

test('midtrans is no longer a supported gateway', function () {
    expect(fn () => PaymentGatewayFactory::make('midtrans'))
        ->toThrow(InvalidArgumentException::class, 'Unsupported payment gateway: midtrans');
});

test('creates xendit gateway', function () {
    $gateway = PaymentGatewayFactory::make('xendit');
    expect($gateway)->toBeInstanceOf(XenditGateway::class);
});

test('creates manual gateway', function () {
    $gateway = PaymentGatewayFactory::make('manual');
    expect($gateway)->toBeInstanceOf(ManualGateway::class);
});

test('uses default gateway from config when null provided', function () {
    Config::set('payment.default_gateway', 'xendit');

    $gateway = PaymentGatewayFactory::make(null);

    expect($gateway)->toBeInstanceOf(XenditGateway::class);
});

test('uses default gateway from config when not specified', function () {
    Config::set('payment.default_gateway', 'manual');

    $gateway = PaymentGatewayFactory::make();

    expect($gateway)->toBeInstanceOf(ManualGateway::class);
});

test('throws exception for unsupported gateway', function () {
    expect(fn () => PaymentGatewayFactory::make('stripe'))
        ->toThrow(InvalidArgumentException::class, 'Unsupported payment gateway: stripe');
});

test('returns available gateways', function () {
    $available = PaymentGatewayFactory::available();

    expect($available)->toBe(['xendit', 'manual', 'cash']);
});

test('checks if gateway is supported', function () {
    expect(PaymentGatewayFactory::supports('midtrans'))->toBeFalse()
        ->and(PaymentGatewayFactory::supports('xendit'))->toBeTrue()
        ->and(PaymentGatewayFactory::supports('manual'))->toBeTrue()
        ->and(PaymentGatewayFactory::supports('stripe'))->toBeFalse()
        ->and(PaymentGatewayFactory::supports('paypal'))->toBeFalse();
});
