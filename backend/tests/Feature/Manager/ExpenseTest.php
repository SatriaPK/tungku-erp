<?php

use App\Enum\PayrollStatus;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payroll;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Sanctum::actingAs(User::factory()->manager()->create());
});

function expensePayload(ExpenseCategory $category, array $overrides = []): array
{
    return array_merge([
        'expense_category_id' => $category->id,
        'description' => 'Pembelian kabel studio',
        'amount' => 125000,
        'spent_on' => '2026-09-15',
    ], $overrides);
}

it('provides manager expense CRUD, filters, pagination and preserves the original recorder on update', function () {
    $category = ExpenseCategory::create(['name' => 'Peralatan', 'is_active' => true]);
    $otherCategory = ExpenseCategory::create(['name' => 'Transportasi', 'is_active' => true]);

    $id = $this->postJson('/api/v1/manager/expenses', expensePayload($category, ['recorded_by' => 999]))
        ->assertCreated()
        ->assertJsonPath('data.amount', 125000)
        ->assertJsonPath('data.recorded_by', auth()->id())
        ->json('data.id');
    $originalRecorderId = Expense::findOrFail($id)->recorded_by;

    $this->getJson('/api/v1/manager/expenses?expense_category_id='.$category->id.'&date_from=2026-09-01&date_to=2026-09-30')
        ->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/manager/expenses?expense_category_id='.$otherCategory->id)
        ->assertOk()->assertJsonCount(0, 'data');
    $this->getJson("/api/v1/manager/expenses/{$id}")->assertOk()->assertJsonPath('data.category.name', 'Peralatan');

    $newManager = User::factory()->manager()->create();
    Sanctum::actingAs($newManager);
    $this->putJson("/api/v1/manager/expenses/{$id}", expensePayload($category, ['amount' => 150000, 'description' => 'Kabel baru']))
        ->assertOk()->assertJsonPath('data.amount', 150000)->assertJsonPath('data.recorded_by', $originalRecorderId);
    expect(Expense::findOrFail($id)->recorded_by)->not->toBe($newManager->id);

    $this->deleteJson("/api/v1/manager/expenses/{$id}")->assertNoContent();
    $this->assertDatabaseMissing('expenses', ['id' => $id]);
});

it('rejects invalid payloads, inactive categories and actor identifiers from the request', function () {
    $activeCategory = ExpenseCategory::create(['name' => 'Aktif', 'is_active' => true]);
    $inactiveCategory = ExpenseCategory::create(['name' => 'Nonaktif', 'is_active' => false]);

    foreach ([0, -1, 1.5, '1.5'] as $amount) {
        $this->postJson('/api/v1/manager/expenses', expensePayload($activeCategory, ['amount' => $amount]))
            ->assertUnprocessable()->assertJsonValidationErrors('amount');
    }
    $this->postJson('/api/v1/manager/expenses', expensePayload($inactiveCategory))
        ->assertUnprocessable()->assertJsonValidationErrors('expense_category_id');
    $this->postJson('/api/v1/manager/expenses', expensePayload($activeCategory, ['spent_on' => 'not-a-date']))
        ->assertUnprocessable()->assertJsonValidationErrors('spent_on');
    $this->postJson('/api/v1/manager/expenses', expensePayload($activeCategory, ['description' => str_repeat('x', 256)]))
        ->assertUnprocessable()->assertJsonValidationErrors('description');

    $this->assertDatabaseCount('expenses', 0);
});

it('rejects expense mutations touching finalized periods without partial changes', function () {
    $category = ExpenseCategory::create(['name' => 'Operasional', 'is_active' => true]);
    $expense = Expense::create([
        'expense_category_id' => $category->id,
        'recorded_by' => auth()->id(),
        'description' => 'Expense lama',
        'amount' => 50000,
        'spent_on' => '2026-09-15',
    ]);
    Payroll::create([
        'period_year' => 2026,
        'period_month' => 10,
        'total_revenue' => 0,
        'total_expense' => 0,
        'net_profit' => 0,
        'status' => PayrollStatus::Finalized,
    ]);

    $this->postJson('/api/v1/manager/expenses', expensePayload($category, ['spent_on' => '2026-10-01']))
        ->assertUnprocessable()->assertJsonValidationErrors('spent_on');
    $this->putJson("/api/v1/manager/expenses/{$expense->id}", expensePayload($category, ['spent_on' => '2026-10-01', 'amount' => 1]))
        ->assertUnprocessable()->assertJsonValidationErrors('spent_on');
    $this->deleteJson("/api/v1/manager/expenses/{$expense->id}")->assertNoContent();

    $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);

    $finalExpense = Expense::create([
        'expense_category_id' => $category->id,
        'recorded_by' => auth()->id(),
        'description' => 'Expense final',
        'amount' => 70000,
        'spent_on' => '2026-10-05',
    ]);
    $this->putJson("/api/v1/manager/expenses/{$finalExpense->id}", expensePayload($category, ['spent_on' => '2026-11-01']))
        ->assertUnprocessable()->assertJsonValidationErrors('spent_on');
    $this->deleteJson("/api/v1/manager/expenses/{$finalExpense->id}")
        ->assertUnprocessable()->assertJsonValidationErrors('spent_on');
    $this->assertDatabaseHas('expenses', ['id' => $finalExpense->id, 'amount' => 70000, 'spent_on' => '2026-10-05 00:00:00']);
});

it('restricts expense endpoints to active managers', function () {
    $category = ExpenseCategory::create(['name' => 'Operasional', 'is_active' => true]);
    Sanctum::actingAs(User::factory()->operator()->create());
    $this->getJson('/api/v1/manager/expenses')->assertForbidden();
    $this->postJson('/api/v1/manager/expenses', expensePayload($category))->assertForbidden();

    Sanctum::actingAs(User::factory()->manager()->inactive()->create());
    $this->getJson('/api/v1/manager/expenses')->assertForbidden();
});

it('requires authentication for expense endpoints', function () {
    $this->app['auth']->forgetGuards();
    $this->getJson('/api/v1/manager/expenses')->assertUnauthorized();
});
