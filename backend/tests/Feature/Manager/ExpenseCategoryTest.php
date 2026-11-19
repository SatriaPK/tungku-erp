<?php

use App\Actions\Expense\EnsureExpensePeriodsAreOpen;
use App\Enum\PayrollStatus;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    // Isolate CRUD fixtures from production reference categories.
    ExpenseCategory::query()->delete();
    Sanctum::actingAs(User::factory()->manager()->create());
});

it('allows a manager to create, list, show, update, deactivate, and delete an unused category', function () {
    $id = $this->postJson('/api/v1/manager/expense-categories', ['name' => 'Listrik'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Listrik')
        ->assertJsonPath('data.is_active', true)
        ->json('data.id');

    $this->getJson('/api/v1/manager/expense-categories')->assertOk()->assertJsonPath('data.0.id', $id);
    $this->getJson("/api/v1/manager/expense-categories/{$id}")->assertOk()->assertJsonPath('data.name', 'Listrik');
    $this->putJson("/api/v1/manager/expense-categories/{$id}", ['name' => 'Listrik dan air', 'is_active' => false])
        ->assertOk()->assertJsonPath('data.is_active', false);
    $this->getJson('/api/v1/manager/expense-categories?active=1')->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/manager/expense-categories?active=0')->assertJsonCount(1, 'data');
    $this->deleteJson("/api/v1/manager/expense-categories/{$id}")->assertNoContent();

    $this->assertDatabaseMissing('expense_categories', ['id' => $id]);
});

it('rejects duplicate and overlong category names', function () {
    ExpenseCategory::create(['name' => 'Transportasi', 'is_active' => true]);

    $this->postJson('/api/v1/manager/expense-categories', ['name' => 'Transportasi'])
        ->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->postJson('/api/v1/manager/expense-categories', ['name' => str_repeat('x', 51)])
        ->assertUnprocessable()->assertJsonValidationErrors('name');
});

it('rejects deleting a category referenced by an expense without deleting the expense', function () {
    $category = ExpenseCategory::create(['name' => 'Peralatan', 'is_active' => true]);
    $expense = Expense::create([
        'expense_category_id' => $category->id,
        'recorded_by' => auth()->id(),
        'description' => 'Pembelian kabel',
        'amount' => 100_000,
        'spent_on' => '2026-10-15',
    ]);

    $this->deleteJson("/api/v1/manager/expense-categories/{$category->id}")
        ->assertUnprocessable()->assertJsonValidationErrors('category');

    $this->assertDatabaseHas('expense_categories', ['id' => $category->id]);
    $this->assertDatabaseHas('expenses', ['id' => $expense->id, 'expense_category_id' => $category->id]);
});

it('preserves inactive categories and historical expense relations', function () {
    $category = ExpenseCategory::create(['name' => 'Sewa studio', 'is_active' => true]);
    $expense = Expense::create([
        'expense_category_id' => $category->id,
        'recorded_by' => auth()->id(),
        'description' => 'Sewa Oktober',
        'amount' => 1_000_000,
        'spent_on' => '2026-10-10',
    ]);

    $this->putJson("/api/v1/manager/expense-categories/{$category->id}", ['name' => 'Sewa studio', 'is_active' => false])
        ->assertOk()->assertJsonPath('data.is_active', false);

    expect($expense->fresh()->category->is_active)->toBeFalse();
    $this->getJson("/api/v1/manager/expense-categories/{$category->id}")
        ->assertOk()->assertJsonPath('data.is_active', false);
});

it('limits category management to active managers', function () {
    Sanctum::actingAs(User::factory()->operator()->create());
    $this->getJson('/api/v1/manager/expense-categories')->assertForbidden();
    $this->postJson('/api/v1/manager/expense-categories', ['name' => 'Listrik'])->assertForbidden();

    Sanctum::actingAs(User::factory()->manager()->inactive()->create());
    $this->getJson('/api/v1/manager/expense-categories')->assertForbidden();
});

it('rejects expense dates in finalized months, including either endpoint date', function () {
    Payroll::create([
        'period_year' => 2026,
        'period_month' => 10,
        'total_revenue' => 0,
        'total_expense' => 0,
        'net_profit' => 0,
        'status' => PayrollStatus::Finalized,
    ]);

    $guard = app(EnsureExpensePeriodsAreOpen::class);
    $guard->handle('2026-09-30', '2026-11-01');

    expect(fn () => $guard->handle('2026-10-01'))->toThrow(ValidationException::class)
        ->and(fn () => $guard->handle('2026-10-31', '2026-11-01'))->toThrow(ValidationException::class);
});

it('creates draft payroll rows to serialize expense guard checks against finalization', function () {
    $guard = app(EnsureExpensePeriodsAreOpen::class);
    $guard->handle('2026-09-30', '2026-11-01');

    $this->assertDatabaseHas('payrolls', [
        'period_year' => 2026,
        'period_month' => 9,
        'total_revenue' => 0,
        'total_expense' => 0,
        'net_profit' => 0,
        'status' => PayrollStatus::Draft->value,
    ]);
    $this->assertDatabaseHas('payrolls', [
        'period_year' => 2026,
        'period_month' => 11,
        'total_revenue' => 0,
        'total_expense' => 0,
        'net_profit' => 0,
        'status' => PayrollStatus::Draft->value,
    ]);
    $this->assertDatabaseCount('payrolls', 2);
});

it('allows open periods and empty date input', function () {
    $guard = app(EnsureExpensePeriodsAreOpen::class);
    $guard->handle('2026-09-30', '2026-11-01');
    $guard->handle();
    expect(true)->toBeTrue();
});
