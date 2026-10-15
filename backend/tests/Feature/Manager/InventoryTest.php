<?php

use App\Enum\InventoryStatus;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Inventory;
use App\Models\InventoryCategory;
use App\Models\Room;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Sanctum::actingAs(User::factory()->manager()->create());
});

function inventoryPayload(InventoryCategory $category, array $overrides = []): array
{
    return array_merge([
        'code' => 'CAM-100',
        'name' => 'Kamera utama',
        'inventory_category_id' => $category->id,
        'brand' => 'Example',
        'purchase_date' => '2026-01-31',
        'purchase_price' => 12_000,
        'residual_value' => 2_000,
        'useful_life_months' => 10,
    ], $overrides);
}

it('creates, reads, updates and soft deletes inventory while calculating its book value', function () {
    $category = InventoryCategory::create(['name' => 'Kamera', 'is_active' => true]);
    $room = Room::factory()->create();
    $inventoryData = inventoryPayload($category, [
        'room_id' => $room->id,
        'created_by' => 999999,
        'status' => 'sold',
        'disposed_on' => '2026-02-01',
        'disposal_value' => 9000,
    ]);

    $id = $this->postJson('/api/v1/manager/inventories', $inventoryData)
        ->assertCreated()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.created_by', null)
        ->assertJsonPath('data.monthly_depreciation', 1000)
        ->assertJsonPath('data.book_value', 2000)
        ->json('data.id');

    $saved = Inventory::findOrFail($id);
    expect($saved->created_by)->toBe(auth()->id())
        ->and($saved->status)->toBe(InventoryStatus::Active)
        ->and($saved->disposed_on)->toBeNull()
        ->and($saved->disposal_value)->toBeNull();

    $this->getJson("/api/v1/manager/inventories/{$id}")->assertOk()->assertJsonPath('data.room.id', $room->id);
    $this->putJson("/api/v1/manager/inventories/{$id}", inventoryPayload($category, ['name' => 'Kamera baru']))
        ->assertOk()->assertJsonPath('data.name', 'Kamera baru');
    $this->deleteJson("/api/v1/manager/inventories/{$id}")->assertNoContent();
    $this->assertSoftDeleted('inventories', ['id' => $id]);
    $this->getJson('/api/v1/manager/inventories')->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/manager/inventories?include_deleted=1')->assertJsonCount(1, 'data');
});

it('validates inventory fields and active category and room', function () {
    $category = InventoryCategory::create(['name' => 'Aktif', 'is_active' => true]);
    $inactive = InventoryCategory::create(['name' => 'Nonaktif', 'is_active' => false]);

    $this->postJson('/api/v1/manager/inventories', inventoryPayload($inactive))
        ->assertUnprocessable()->assertJsonValidationErrors('inventory_category_id');
    $this->postJson('/api/v1/manager/inventories', inventoryPayload($category, ['code' => str_repeat('x', 31)]))
        ->assertUnprocessable()->assertJsonValidationErrors('code');
    $this->postJson('/api/v1/manager/inventories', inventoryPayload($category, ['purchase_price' => 100, 'residual_value' => 101]))
        ->assertUnprocessable()->assertJsonValidationErrors('residual_value');
    $this->postJson('/api/v1/manager/inventories', inventoryPayload($category, ['purchase_price' => -1]))
        ->assertUnprocessable()->assertJsonValidationErrors('purchase_price');
    $this->postJson('/api/v1/manager/inventories', inventoryPayload($category, ['purchase_price' => 1.5]))
        ->assertUnprocessable()->assertJsonValidationErrors('purchase_price');
    $this->postJson('/api/v1/manager/inventories', inventoryPayload($category, ['useful_life_months' => 0]))
        ->assertUnprocessable()->assertJsonValidationErrors('useful_life_months');
    $this->postJson('/api/v1/manager/inventories', inventoryPayload($category, ['useful_life_months' => 65536]))
        ->assertUnprocessable()->assertJsonValidationErrors('useful_life_months');
    $this->postJson('/api/v1/manager/inventories', inventoryPayload($category, ['purchase_date' => 'not-a-date']))
        ->assertUnprocessable()->assertJsonValidationErrors('purchase_date');

    $inactiveRoom = Room::factory()->inactive()->create();
    $this->postJson('/api/v1/manager/inventories', inventoryPayload($category, ['room_id' => $inactiveRoom->id]))
        ->assertUnprocessable()->assertJsonValidationErrors('room_id');
});

it('supports nullable expense links without creating or modifying expenses', function () {
    $category = InventoryCategory::create(['name' => 'Aset', 'is_active' => true]);
    $expenseCategory = ExpenseCategory::create(['name' => 'Pembelian', 'is_active' => true]);
    $expense = Expense::create([
        'expense_category_id' => $expenseCategory->id,
        'recorded_by' => auth()->id(),
        'description' => 'Pembelian kamera',
        'amount' => 12000,
        'spent_on' => '2026-01-31',
    ]);

    $this->postJson('/api/v1/manager/inventories', inventoryPayload($category, ['expense_id' => $expense->id]))
        ->assertCreated()->assertJsonPath('data.expense.id', $expense->id);

    $this->assertDatabaseCount('expenses', 1);
    $this->assertDatabaseHas('expenses', ['id' => $expense->id, 'amount' => 12000]);
});

it('keeps optional expense links referential without changing expenses or finance totals', function () {
    $category = InventoryCategory::create(['name' => 'Speaker', 'is_active' => true]);
    $expenseCategory = ExpenseCategory::create(['name' => 'Peralatan', 'is_active' => true]);
    $expense = Expense::create([
        'expense_category_id' => $expenseCategory->id,
        'recorded_by' => auth()->id(),
        'description' => 'Speaker studio',
        'amount' => 80000,
        'spent_on' => '2026-01-15',
    ]);

    $id = $this->postJson('/api/v1/manager/inventories', inventoryPayload($category, [
        'expense_id' => $expense->id,
        'purchase_price' => 80000,
        'residual_value' => 0,
    ]))->assertCreated()
        ->assertJsonPath('data.expense.id', $expense->id)
        ->assertJsonPath('data.expense.description', 'Speaker studio')
        ->json('data.id');

    expect($expense->inventories()->count())->toBe(1);
    $this->putJson("/api/v1/manager/inventories/{$id}", inventoryPayload($category, [
        'expense_id' => null,
        'purchase_price' => 80000,
        'residual_value' => 0,
    ]))->assertOk()->assertJsonPath('data.expense_id', null)->assertJsonPath('data.expense', null);
    $this->putJson("/api/v1/manager/inventories/{$id}", inventoryPayload($category, [
        'expense_id' => $expense->id,
        'purchase_price' => 80000,
        'residual_value' => 0,
    ]))->assertOk()->assertJsonPath('data.expense.id', $expense->id);

    $this->postJson("/api/v1/manager/inventories/{$id}/dispose", [
        'type' => 'sold', 'disposed_on' => '2026-02-01', 'disposal_value' => 25000,
    ])->assertOk();
    $this->assertDatabaseHas('expenses', ['id' => $expense->id, 'amount' => 80000, 'description' => 'Speaker studio']);
    $this->assertDatabaseCount('expenses', 1);
    $this->getJson('/api/v1/manager/finance/monthly-summary?year=2026&month=1')
        ->assertOk()->assertJsonPath('data.totals.expense', 80000)
        ->assertJsonPath('data.totals.net_profit', -80000);

    $this->deleteJson("/api/v1/manager/expenses/{$expense->id}")->assertNoContent();
    $this->assertDatabaseHas('inventories', ['id' => $id, 'expense_id' => null]);
    $this->getJson("/api/v1/manager/inventories/{$id}")->assertOk()->assertJsonPath('data.expense_id', null);
});

it('filters and paginates active inventory and rejects status or disposal writes on update', function () {
    $category = InventoryCategory::create(['name' => 'Tripod', 'is_active' => true]);
    Inventory::create([
        'code' => 'TRI-1', 'name' => 'Tripod satu', 'inventory_category_id' => $category->id,
        'purchase_date' => '2026-01-01', 'purchase_price' => 1000, 'residual_value' => 0,
        'useful_life_months' => 12, 'status' => InventoryStatus::Active,
        'created_by' => auth()->id(),
    ]);
    Inventory::create([
        'code' => 'TRI-2', 'name' => 'Tripod dua', 'inventory_category_id' => $category->id,
        'purchase_date' => '2026-01-01', 'purchase_price' => 1000, 'residual_value' => 0,
        'useful_life_months' => 12, 'status' => InventoryStatus::InRepair,
        'created_by' => auth()->id(),
    ]);

    $response = $this->getJson('/api/v1/manager/inventories?status=active&search=TRI&per_page=1');
    $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.per_page', 1)->assertJsonPath('meta.total', 1);

    $id = $response->json('data.0.id');
    $this->putJson("/api/v1/manager/inventories/{$id}", inventoryPayload($category, ['status' => 'sold', 'disposed_on' => '2026-02-01']))
        ->assertOk()->assertJsonPath('data.status', 'active')->assertJsonPath('data.disposed_on', null);
});

it('limits inventory endpoints to active managers', function () {
    Sanctum::actingAs(User::factory()->operator()->create());
    $this->getJson('/api/v1/manager/inventories')->assertForbidden();
    $this->postJson('/api/v1/manager/inventories', [])->assertForbidden();

    Sanctum::actingAs(User::factory()->manager()->inactive()->create());
    $this->getJson('/api/v1/manager/inventories')->assertForbidden();
});

it('sells an inventory item and requires a nonnegative sale value', function () {
    $category = InventoryCategory::create(['name' => 'Lensa', 'is_active' => true]);
    $id = $this->postJson('/api/v1/manager/inventories', inventoryPayload($category))
        ->assertCreated()->json('data.id');

    $this->postJson("/api/v1/manager/inventories/{$id}/dispose", [
        'type' => 'sold', 'disposed_on' => '2026-02-15', 'disposal_value' => 5000,
    ])->assertOk()->assertJsonPath('data.status', 'sold')
        ->assertJsonPath('data.disposed_on', '2026-02-15')
        ->assertJsonPath('data.disposal_value', 5000)
        ->assertJsonPath('data.book_value', 11000);

    $this->assertDatabaseHas('inventories', ['id' => $id, 'status' => 'sold', 'disposal_value' => 5000]);
});

it('disposes a damaged inventory without a disposal value or linked expense', function () {
    $category = InventoryCategory::create(['name' => 'Kabel XLR', 'is_active' => true]);
    $id = $this->postJson('/api/v1/manager/inventories', inventoryPayload($category))
        ->assertCreated()->json('data.id');

    $this->postJson("/api/v1/manager/inventories/{$id}/dispose", [
        'type' => 'disposed', 'disposed_on' => '2026-01-31',
    ])->assertOk()->assertJsonPath('data.status', 'disposed')
        ->assertJsonPath('data.disposal_value', null)
        ->assertJsonPath('data.book_value', 12000);

    $this->assertDatabaseHas('inventories', [
        'id' => $id, 'status' => 'disposed', 'disposed_on' => '2026-01-31 00:00:00',
        'disposal_value' => null, 'expense_id' => null,
    ]);
    $this->assertDatabaseCount('expenses', 0);
});

it('rejects invalid disposal transitions, dates, values, and attempts on soft-deleted assets', function () {
    $category = InventoryCategory::create(['name' => 'Mixer', 'is_active' => true]);
    $id = $this->postJson('/api/v1/manager/inventories', inventoryPayload($category))
        ->assertCreated()->json('data.id');

    $this->postJson("/api/v1/manager/inventories/{$id}/dispose", ['type' => 'sold', 'disposed_on' => '2026-02-01'])
        ->assertUnprocessable()->assertJsonValidationErrors('disposal_value');
    $this->postJson("/api/v1/manager/inventories/{$id}/dispose", [
        'type' => 'sold', 'disposed_on' => '2026-02-01', 'disposal_value' => -1,
    ])->assertUnprocessable()->assertJsonValidationErrors('disposal_value');
    $this->postJson("/api/v1/manager/inventories/{$id}/dispose", [
        'type' => 'disposed', 'disposed_on' => '2025-12-31',
    ])->assertUnprocessable()->assertJsonValidationErrors('disposed_on');
    $this->assertDatabaseHas('inventories', ['id' => $id, 'status' => 'active', 'disposed_on' => null]);

    $this->postJson("/api/v1/manager/inventories/{$id}/dispose", [
        'type' => 'sold', 'disposed_on' => '2026-02-01', 'disposal_value' => 500,
    ])->assertOk();
    $this->postJson("/api/v1/manager/inventories/{$id}/dispose", [
        'type' => 'disposed', 'disposed_on' => '2026-03-01',
    ])->assertUnprocessable()->assertJsonValidationErrors('inventory');
    $this->putJson("/api/v1/manager/inventories/{$id}", inventoryPayload($category))
        ->assertUnprocessable()->assertJsonValidationErrors('inventory');
    $this->deleteJson("/api/v1/manager/inventories/{$id}")
        ->assertUnprocessable()->assertJsonValidationErrors('inventory');

    $this->postJson('/api/v1/manager/inventories', inventoryPayload($category, ['code' => 'MIX-DEL']))
        ->assertCreated();
    $deleted = Inventory::where('code', 'MIX-DEL')->firstOrFail();
    $this->deleteJson("/api/v1/manager/inventories/{$deleted->id}")->assertNoContent();
    $this->postJson("/api/v1/manager/inventories/{$deleted->id}/dispose", [
        'type' => 'disposed', 'disposed_on' => '2026-02-01',
    ])->assertNotFound();
});
