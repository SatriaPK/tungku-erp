<?php

use App\Models\Package;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

beforeEach(function () {
    $this->client = User::factory()->create();
    $this->manager = User::factory()->manager()->create();
    $this->operator = User::factory()->operator()->create();
});

// ─── Get Active Packages ─────────────────────────────────────────────────────

test('client can get active packages', function () {
    $activePackage = Package::factory()->create([
        'name' => 'Active Package',
        'is_active' => true,
    ]);

    Package::factory()->create([
        'name' => 'Inactive Package',
        'is_active' => false,
    ]);

    actingAs($this->client);

    $response = getJson('/api/v1/client/packages');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment(['name' => 'Active Package'])
        ->assertJsonMissing(['name' => 'Inactive Package']);
});

test('response includes price from accessor', function () {
    $package = Package::factory()->create([
        'auto_price' => 5000000,
        'manual_price' => null,
        'is_price_manual' => false,
        'is_active' => true,
    ]);

    actingAs($this->client);

    $response = getJson('/api/v1/client/packages');

    $response->assertOk()
        ->assertJsonFragment([
            'id' => $package->id,
            'price' => 5000000,
        ]);
});

test('response does not include auto_price and manual_price', function () {
    Package::factory()->create([
        'auto_price' => 5000000,
        'manual_price' => 6000000,
        'is_price_manual' => true,
        'is_active' => true,
    ]);

    actingAs($this->client);

    $response = getJson('/api/v1/client/packages');

    $response->assertOk();

    // Should not have internal pricing fields
    expect($response->json('data.0'))->not->toHaveKey('auto_price');
    expect($response->json('data.0'))->not->toHaveKey('manual_price');
    expect($response->json('data.0'))->not->toHaveKey('is_price_manual');
    expect($response->json('data.0'))->not->toHaveKey('is_active');
    expect($response->json('data.0'))->not->toHaveKey('created_by');
});

test('package with is_price_manual true returns manual_price as price', function () {
    $package = Package::factory()->create([
        'auto_price' => 5000000,
        'manual_price' => 6000000,
        'is_price_manual' => true,
        'is_active' => true,
    ]);

    actingAs($this->client);

    $response = getJson('/api/v1/client/packages');

    $response->assertOk()
        ->assertJsonFragment([
            'id' => $package->id,
            'price' => 6000000,
        ]);
});

test('package with is_price_manual false returns auto_price as price', function () {
    $package = Package::factory()->create([
        'auto_price' => 5000000,
        'manual_price' => 6000000,
        'is_price_manual' => false,
        'is_active' => true,
    ]);

    actingAs($this->client);

    $response = getJson('/api/v1/client/packages');

    $response->assertOk()
        ->assertJsonFragment([
            'id' => $package->id,
            'price' => 5000000,
        ]);
});

test('inactive packages are not returned', function () {
    Package::factory()->create(['is_active' => true]);
    Package::factory()->create(['is_active' => false]);
    Package::factory()->create(['is_active' => false]);

    actingAs($this->client);

    $response = getJson('/api/v1/client/packages');

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

test('manager cannot access package catalog', function () {
    Package::factory()->create(['is_active' => true]);

    actingAs($this->manager);

    $response = getJson('/api/v1/client/packages');

    $response->assertForbidden();
});

test('operator cannot access package catalog', function () {
    Package::factory()->create(['is_active' => true]);

    actingAs($this->operator);

    $response = getJson('/api/v1/client/packages');

    $response->assertForbidden();
});

test('unauthenticated user cannot access package catalog', function () {
    Package::factory()->create(['is_active' => true]);

    $response = getJson('/api/v1/client/packages');

    $response->assertUnauthorized();
});

test('catalog returns all include fields', function () {
    $package = Package::factory()->create([
        'include_recording' => true,
        'include_editing' => true,
        'include_mixing' => false,
        'include_mastering' => true,
        'is_active' => true,
    ]);

    actingAs($this->client);

    $response = getJson('/api/v1/client/packages');

    $response->assertOk()
        ->assertJsonFragment([
            'id' => $package->id,
            'include_recording' => true,
            'include_editing' => true,
            'include_mixing' => false,
            'include_mastering' => true,
        ]);
});

test('catalog returns required fields', function () {
    Package::factory()->create(['is_active' => true]);

    actingAs($this->client);

    $response = getJson('/api/v1/client/packages');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'description',
                    'track_count',
                    'recording_hours',
                    'include_recording',
                    'include_editing',
                    'include_mixing',
                    'include_mastering',
                    'price',
                ],
            ],
        ]);
});

test('packages are ordered by name', function () {
    Package::factory()->create(['name' => 'Zebra Package', 'is_active' => true]);
    Package::factory()->create(['name' => 'Alpha Package', 'is_active' => true]);
    Package::factory()->create(['name' => 'Bravo Package', 'is_active' => true]);

    actingAs($this->client);

    $response = getJson('/api/v1/client/packages');

    $response->assertOk();

    $names = collect($response->json('data'))->pluck('name')->toArray();

    expect($names)->toBe(['Alpha Package', 'Bravo Package', 'Zebra Package']);
});
