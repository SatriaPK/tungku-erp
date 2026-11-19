<?php

use App\Models\Inventory;
use App\Models\InventoryCategory;
use App\Models\Room;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    // Isolate CRUD fixtures from production reference categories.
    InventoryCategory::query()->delete();
    Sanctum::actingAs(User::factory()->manager()->create());
});

it('allows a manager to create, list, show, update, deactivate, and delete an unused inventory category', function () {
    $id = $this->postJson('/api/v1/manager/inventory-categories', ['name' => 'Audio'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Audio')
        ->assertJsonPath('data.is_active', true)
        ->json('data.id');

    $this->getJson('/api/v1/manager/inventory-categories')->assertOk()->assertJsonPath('data.0.id', $id);
    $this->getJson("/api/v1/manager/inventory-categories/{$id}")->assertOk()->assertJsonPath('data.name', 'Audio');
    $this->putJson("/api/v1/manager/inventory-categories/{$id}", ['name' => 'Audio dan alat musik', 'is_active' => false])
        ->assertOk()->assertJsonPath('data.is_active', false);
    $this->getJson('/api/v1/manager/inventory-categories?active=1')->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/manager/inventory-categories?active=0')->assertJsonCount(1, 'data');
    $this->deleteJson("/api/v1/manager/inventory-categories/{$id}")->assertNoContent();

    $this->assertDatabaseMissing('inventory_categories', ['id' => $id]);
});

it('rejects duplicate and overlong inventory category names', function () {
    InventoryCategory::create(['name' => 'Kamera', 'is_active' => true]);

    $this->postJson('/api/v1/manager/inventory-categories', ['name' => 'Kamera'])
        ->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->postJson('/api/v1/manager/inventory-categories', ['name' => str_repeat('x', 51)])
        ->assertUnprocessable()->assertJsonValidationErrors('name');
});

it('rejects deleting a category referenced by inventory without deleting the inventory', function () {
    $category = InventoryCategory::create(['name' => 'Peralatan rekam', 'is_active' => true]);
    $room = Room::create(['name' => 'Studio A', 'is_active' => true]);
    $manager = auth()->user();
    $inventory = Inventory::create([
        'code' => 'MIC-001',
        'name' => 'Mikrofon',
        'inventory_category_id' => $category->id,
        'purchase_date' => '2026-01-01',
        'purchase_price' => 1_000_000,
        'residual_value' => 100_000,
        'useful_life_months' => 36,
        'status' => 'active',
        'room_id' => $room->id,
        'created_by' => $manager->id,
    ]);

    $this->deleteJson("/api/v1/manager/inventory-categories/{$category->id}")
        ->assertUnprocessable()->assertJsonValidationErrors('category');

    $this->assertDatabaseHas('inventory_categories', ['id' => $category->id]);
    $this->assertDatabaseHas('inventories', ['id' => $inventory->id, 'inventory_category_id' => $category->id]);
});

it('preserves inactive categories for existing inventory history', function () {
    $category = InventoryCategory::create(['name' => 'Kabel', 'is_active' => true]);

    $this->putJson("/api/v1/manager/inventory-categories/{$category->id}", ['name' => 'Kabel', 'is_active' => false])
        ->assertOk()->assertJsonPath('data.is_active', false);

    expect($category->fresh()->is_active)->toBeFalse();
    $this->getJson("/api/v1/manager/inventory-categories/{$category->id}")
        ->assertOk()->assertJsonPath('data.is_active', false);
});

it('limits inventory category management to active managers', function () {
    Sanctum::actingAs(User::factory()->operator()->create());
    $this->getJson('/api/v1/manager/inventory-categories')->assertForbidden();
    $this->postJson('/api/v1/manager/inventory-categories', ['name' => 'Audio'])->assertForbidden();

    Sanctum::actingAs(User::factory()->manager()->inactive()->create());
    $this->getJson('/api/v1/manager/inventory-categories')->assertForbidden();
});
