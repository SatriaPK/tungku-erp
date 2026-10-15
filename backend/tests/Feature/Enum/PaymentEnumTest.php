<?php

use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;

// ─── PaymentProvider Tests ───────────────────────────────────────────────────

test('PaymentProvider::Cash has correct value', function () {
    expect(PaymentProvider::Cash->value)->toBe('cash');
});

test('PaymentProvider::Cash label returns Tunai', function () {
    expect(PaymentProvider::Cash->label())->toBe('Tunai');
});

test('PaymentProvider::Cash requiresGateway returns false', function () {
    expect(PaymentProvider::Cash->requiresGateway())->toBeFalse();
});

test('PaymentProvider::Midtrans requiresGateway returns true', function () {
    expect(PaymentProvider::Midtrans->requiresGateway())->toBeTrue();
});

test('PaymentProvider::Xendit requiresGateway returns true', function () {
    expect(PaymentProvider::Xendit->requiresGateway())->toBeTrue();
});

test('PaymentProvider values include cash', function () {
    expect(PaymentProvider::values())->toContain('cash');
});

test('PaymentProvider options include Cash with label Tunai', function () {
    $options = PaymentProvider::options();
    $cashOption = collect($options)->firstWhere('value', 'cash');

    expect($cashOption)->not->toBeNull()
        ->and($cashOption['label'])->toBe('Tunai');
});

// ─── PaymentStatus Tests ─────────────────────────────────────────────────────

test('PaymentStatus::PendingManualConfirmation has correct value', function () {
    expect(PaymentStatus::PendingManualConfirmation->value)->toBe('pending_manual_confirmation');
});

test('PaymentStatus::PendingManualConfirmation label returns correct text', function () {
    expect(PaymentStatus::PendingManualConfirmation->label())->toBe('Menunggu Konfirmasi Manual');
});

test('PaymentStatus::Cancelled has correct value', function () {
    expect(PaymentStatus::Cancelled->value)->toBe('cancelled');
});

test('PaymentStatus::Cancelled label returns Dibatalkan', function () {
    expect(PaymentStatus::Cancelled->label())->toBe('Dibatalkan');
});

// ─── PaymentStatus Transition Tests ──────────────────────────────────────────

test('PendingManualConfirmation can transition to Paid', function () {
    expect(PaymentStatus::PendingManualConfirmation->canTransitionTo(PaymentStatus::Paid))->toBeTrue();
});

test('PendingManualConfirmation can transition to Cancelled', function () {
    expect(PaymentStatus::PendingManualConfirmation->canTransitionTo(PaymentStatus::Cancelled))->toBeTrue();
});

test('PendingManualConfirmation cannot transition to Pending', function () {
    expect(PaymentStatus::PendingManualConfirmation->canTransitionTo(PaymentStatus::Pending))->toBeFalse();
});

test('PendingManualConfirmation cannot transition to Failed', function () {
    expect(PaymentStatus::PendingManualConfirmation->canTransitionTo(PaymentStatus::Failed))->toBeFalse();
});

test('PendingManualConfirmation cannot transition to Expired', function () {
    expect(PaymentStatus::PendingManualConfirmation->canTransitionTo(PaymentStatus::Expired))->toBeFalse();
});

test('PendingManualConfirmation cannot transition to Refunded', function () {
    expect(PaymentStatus::PendingManualConfirmation->canTransitionTo(PaymentStatus::Refunded))->toBeFalse();
});

test('Paid cannot transition to PendingManualConfirmation', function () {
    expect(PaymentStatus::Paid->canTransitionTo(PaymentStatus::PendingManualConfirmation))->toBeFalse();
});

test('Cancelled is a terminal status', function () {
    expect(PaymentStatus::Cancelled->isTerminal())->toBeTrue();
});

test('PendingManualConfirmation is not a terminal status', function () {
    expect(PaymentStatus::PendingManualConfirmation->isTerminal())->toBeFalse();
});

// ─── Existing Transition Integrity Tests ─────────────────────────────────────

test('Pending can still transition to Paid, Failed, and Expired', function () {
    expect(PaymentStatus::Pending->canTransitionTo(PaymentStatus::Paid))->toBeTrue()
        ->and(PaymentStatus::Pending->canTransitionTo(PaymentStatus::Failed))->toBeTrue()
        ->and(PaymentStatus::Pending->canTransitionTo(PaymentStatus::Expired))->toBeTrue();
});

test('Pending cannot transition to PendingManualConfirmation or Cancelled', function () {
    expect(PaymentStatus::Pending->canTransitionTo(PaymentStatus::PendingManualConfirmation))->toBeFalse()
        ->and(PaymentStatus::Pending->canTransitionTo(PaymentStatus::Cancelled))->toBeFalse();
});

test('Paid can still transition to Refunded only', function () {
    expect(PaymentStatus::Paid->canTransitionTo(PaymentStatus::Refunded))->toBeTrue()
        ->and(PaymentStatus::Paid->canTransitionTo(PaymentStatus::Pending))->toBeFalse()
        ->and(PaymentStatus::Paid->canTransitionTo(PaymentStatus::Cancelled))->toBeFalse();
});

test('PaymentStatus values include all expected cases', function () {
    $values = PaymentStatus::values();

    expect($values)->toContain('pending')
        ->and($values)->toContain('pending_manual_confirmation')
        ->and($values)->toContain('paid')
        ->and($values)->toContain('failed')
        ->and($values)->toContain('expired')
        ->and($values)->toContain('cancelled')
        ->and($values)->toContain('refunded');
});

test('PendingManualConfirmation isPaid returns false', function () {
    expect(PaymentStatus::PendingManualConfirmation->isPaid())->toBeFalse();
});
