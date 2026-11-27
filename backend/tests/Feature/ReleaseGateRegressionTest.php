<?php

use App\Actions\Finance\FinalizeMonthlyPayroll;
use App\Actions\Inventory\DisposeInventory;
use App\Actions\Inventory\SaveInventory;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Inventory;
use App\Models\InventoryCategory;
use App\Models\Payment;
use App\Models\Payroll;
use App\Models\User;

test('zero and negative profit finalize with zero operator and studio payouts', function (int $expenseAmount) {
    $this->travelTo(now()->setDate(2026, 10, 24));
    $manager = User::factory()->manager()->create();
    $operator = User::factory()->operator()->create();
    $payroll = Payroll::create(['period_year' => 2026, 'period_month' => 9, 'status' => 'draft', 'studio_profit_percentage' => '50.00']);
    $item = $payroll->items()->create(['operator_id' => $operator->id, 'jobs_count' => 1, 'recommended_percentage' => '50.00', 'percentage' => '50.00', 'amount' => 0]);
    Payment::factory()->paid()->create(['amount' => 100, 'paid_at' => '2026-09-15 12:00:00']);
    $category = ExpenseCategory::create(['name' => 'Sewa', 'is_active' => false]);
    Expense::create(['expense_category_id' => $category->id, 'recorded_by' => $manager->id,
        'description' => 'Historic expense', 'spent_on' => '2026-09-15', 'amount' => $expenseAmount]);

    $snapshot = app(FinalizeMonthlyPayroll::class)->handle(2026, 9, $manager);

    expect($snapshot->net_profit)->toBe(100 - $expenseAmount);
    expect($snapshot->total_expense)->toBe($expenseAmount);
    expect($snapshot->studio_profit_amount)->toBe(0);
    expect($item->fresh()->amount)->toBe(0);
})->with(['zero' => 100, 'negative' => 200]);

test('inventory disposal failure rolls back terminal state and disposal metadata', function () {
    $manager = User::factory()->manager()->create();
    $category = InventoryCategory::create(['name' => 'Kamera', 'is_active' => true]);
    $inventory = app(SaveInventory::class)->handle(['code' => 'CAM-ROLLBACK', 'name' => 'Kamera',
        'inventory_category_id' => $category->id, 'purchase_date' => '2026-01-01', 'purchase_price' => 12000,
        'residual_value' => 2000, 'useful_life_months' => 10], $manager);
    Inventory::updated(function (): void {
        throw new RuntimeException('Injected disposal failure');
    });

    expect(fn () => app(DisposeInventory::class)->handle($inventory, ['type' => 'sold', 'disposed_on' => '2026-02-01', 'disposal_value' => 5000]))
        ->toThrow(RuntimeException::class, 'Injected disposal failure');

    $saved = $inventory->fresh();
    expect($saved->status->value)->toBe('active');
    expect($saved->disposed_on)->toBeNull();
    expect($saved->disposal_value)->toBeNull();
    expect(Expense::count())->toBe(0);
});
