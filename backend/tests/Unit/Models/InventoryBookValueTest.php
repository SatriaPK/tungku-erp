<?php

use App\Enum\InventoryStatus;
use App\Models\Inventory;
use Carbon\Carbon;

function inventoryForBookValue(array $attributes = []): Inventory
{
    return new Inventory(array_merge([
        'purchase_date' => '2026-01-31',
        'purchase_price' => 12000,
        'residual_value' => 2000,
        'useful_life_months' => 10,
        'status' => InventoryStatus::Active,
    ], $attributes));
}

it('depreciates a full calendar month starting in the purchase month', function () {
    $inventory = inventoryForBookValue();

    expect($inventory->monthlyDepreciation())->toBe(1000)
        ->and($inventory->bookValue(Carbon::parse('2026-01-01')))->toBe(11000)
        ->and($inventory->bookValue(Carbon::parse('2026-01-31')))->toBe(11000);
});

it('counts each calendar month without prorating partial months', function () {
    $inventory = inventoryForBookValue();

    expect($inventory->bookValue(Carbon::parse('2026-02-01')))->toBe(10000)
        ->and($inventory->bookValue(Carbon::parse('2026-02-28')))->toBe(10000);
});

it('does not depreciate before the purchase month', function () {
    expect(inventoryForBookValue()->bookValue(Carbon::parse('2025-12-31')))->toBe(12000);
});

it('stops depreciating in the disposal month', function () {
    $inventory = inventoryForBookValue([
        'status' => InventoryStatus::Sold,
        'disposed_on' => '2026-03-15',
        'disposal_value' => 8000,
    ]);

    expect($inventory->bookValue(Carbon::parse('2026-12-31')))->toBe(10000);
});

it('does not depreciate an asset disposed in its purchase month', function () {
    $inventory = inventoryForBookValue([
        'status' => InventoryStatus::Disposed,
        'disposed_on' => '2026-01-31',
    ]);

    expect($inventory->bookValue(Carbon::parse('2026-12-31')))->toBe(12000);
});

it('clamps accumulated depreciation at the residual value after useful life', function () {
    $inventory = inventoryForBookValue();

    expect($inventory->bookValue(Carbon::parse('2026-10-01')))->toBe(2000)
        ->and($inventory->bookValue(Carbon::parse('2027-01-01')))->toBe(2000);
});

it('rounds monthly depreciation and never goes below residual value', function () {
    $inventory = inventoryForBookValue([
        'purchase_price' => 100,
        'residual_value' => 0,
        'useful_life_months' => 6,
    ]);

    expect($inventory->monthlyDepreciation())->toBe(17)
        ->and($inventory->bookValue(Carbon::parse('2026-06-01')))->toBe(0);
});

it('uses the current calendar month for active assets by default', function () {
    Carbon::setTestNow('2026-02-02 00:01:00');

    try {
        expect(inventoryForBookValue()->bookValue())->toBe(10000);
    } finally {
        Carbon::setTestNow();
    }
});
