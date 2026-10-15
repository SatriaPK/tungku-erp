<?php

use App\Enum\PaymentStatus;
use App\Enum\PaymentType;
use App\Enum\PayrollStatus;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\Payroll;
use App\Models\Project;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Sanctum::actingAs(User::factory()->manager()->create());
});

function summaryPayment(string $status, ?string $paidAt, int $amount): Payment
{
    $project = Project::factory()->create();

    return Payment::factory()->create([
        'project_id' => $project->id,
        'type' => PaymentType::Po,
        'status' => $status,
        'paid_at' => $paidAt,
        'amount' => $amount,
    ]);
}

it('summarizes paid revenue by Jakarta month boundaries and expenses by category', function () {
    summaryPayment(PaymentStatus::Paid->value, '2025-12-31 23:59:59', 32000);
    summaryPayment(PaymentStatus::Paid->value, '2026-01-01 00:00:00', 1000);
    summaryPayment(PaymentStatus::Paid->value, '2026-01-31 23:59:59', 2000);
    summaryPayment(PaymentStatus::Paid->value, '2026-02-01 00:00:00', 4000);
    summaryPayment(PaymentStatus::Pending->value, '2026-01-15 00:00:00', 8000);
    summaryPayment(PaymentStatus::Paid->value, null, 16000);

    $equipment = ExpenseCategory::create(['name' => 'Peralatan', 'is_active' => true]);
    $utilities = ExpenseCategory::create(['name' => 'Utilitas', 'is_active' => false]);
    foreach ([[$equipment, '2026-01-01', 500], [$equipment, '2026-01-31', 700], [$utilities, '2026-01-15', 900], [$equipment, '2026-02-01', 2000]] as [$category, $date, $amount]) {
        Expense::create([
            'expense_category_id' => $category->id,
            'recorded_by' => auth()->id(),
            'description' => 'Uji laporan',
            'amount' => $amount,
            'spent_on' => $date,
        ]);
    }

    $this->getJson('/api/v1/manager/finance/monthly-summary?year=2026&month=1')
        ->assertOk()
        ->assertJsonPath('data.period.timezone', 'Asia/Jakarta')
        ->assertJsonPath('data.totals.revenue', 3000)
        ->assertJsonPath('data.totals.expense', 2100)
        ->assertJsonPath('data.totals.net_profit', 900)
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonCount(2, 'data.expenses_by_category')
        ->assertJsonPath('data.expenses_by_category.0.category_name', 'Peralatan')
        ->assertJsonPath('data.expenses_by_category.0.amount', 1200);
});

it('handles leap February, no transactions and negative net profit', function () {
    $category = ExpenseCategory::create(['name' => 'Sewa', 'is_active' => true]);
    Expense::create([
        'expense_category_id' => $category->id,
        'recorded_by' => auth()->id(),
        'description' => 'Sewa akhir Februari',
        'amount' => 100,
        'spent_on' => '2024-02-29',
    ]);
    summaryPayment(PaymentStatus::Paid->value, '2024-02-29 23:59:59', 50);
    summaryPayment(PaymentStatus::Paid->value, '2024-03-01 00:00:00', 500);

    $this->getJson('/api/v1/manager/finance/monthly-summary?year=2024&month=2')
        ->assertOk()
        ->assertJsonPath('data.totals.revenue', 50)
        ->assertJsonPath('data.totals.expense', 100)
        ->assertJsonPath('data.totals.net_profit', -50);

    $this->getJson('/api/v1/manager/finance/monthly-summary?year=2024&month=3')
        ->assertOk()
        ->assertJsonPath('data.totals.revenue', 500)
        ->assertJsonPath('data.totals.expense', 0)
        ->assertJsonPath('data.totals.net_profit', 500);
});

it('reflects payment status changes in draft summaries but not after finalization', function () {
    $payment = summaryPayment(PaymentStatus::Pending->value, '2026-01-15 00:00:00', 700);

    $this->getJson('/api/v1/manager/finance/monthly-summary?year=2026&month=1')
        ->assertOk()
        ->assertJsonPath('data.totals.revenue', 0);

    $payment->update(['status' => PaymentStatus::Paid]);
    $this->getJson('/api/v1/manager/finance/monthly-summary?year=2026&month=1')
        ->assertOk()
        ->assertJsonPath('data.totals.revenue', 700);

    $payment->update(['status' => PaymentStatus::Pending]);
    $this->getJson('/api/v1/manager/finance/monthly-summary?year=2026&month=1')
        ->assertOk()
        ->assertJsonPath('data.totals.revenue', 0);

    $payment->update(['status' => PaymentStatus::Paid]);
    $this->postJson('/api/v1/manager/finance/payroll-finalization', ['year' => 2026, 'month' => 1])
        ->assertOk()
        ->assertJsonPath('data.status', 'finalized');

    $this->assertDatabaseHas('payrolls', [
        'period_year' => 2026,
        'period_month' => 1,
        'total_revenue' => 700,
    ]);

    $payment->update(['status' => PaymentStatus::Pending]);
    $this->getJson('/api/v1/manager/finance/monthly-summary?year=2026&month=1')
        ->assertOk()
        ->assertJsonPath('data.totals.revenue', 700)
        ->assertJsonPath('data.status', 'finalized');
});

it('returns finalized snapshot totals instead of current live expenses', function () {
    Payroll::create([
        'period_year' => 2026,
        'period_month' => 1,
        'total_revenue' => 5000,
        'total_expense' => 2000,
        'net_profit' => 3000,
        'status' => PayrollStatus::Finalized,
    ]);
    summaryPayment(PaymentStatus::Paid->value, '2026-01-15 00:00:00', 999999);

    $this->getJson('/api/v1/manager/finance/monthly-summary?year=2026&month=1')
        ->assertOk()
        ->assertJsonPath('data.totals.revenue', 5000)
        ->assertJsonPath('data.totals.expense', 2000)
        ->assertJsonPath('data.totals.net_profit', 3000)
        ->assertJsonPath('data.status', 'finalized');
});

it('validates the requested period and restricts access to active managers', function () {
    $this->getJson('/api/v1/manager/finance/monthly-summary?year=2026&month=13')
        ->assertUnprocessable()->assertJsonValidationErrors('month');
    $this->getJson('/api/v1/manager/finance/monthly-summary?year=2026')
        ->assertUnprocessable()->assertJsonValidationErrors('month');

    Sanctum::actingAs(User::factory()->operator()->create());
    $this->getJson('/api/v1/manager/finance/monthly-summary?year=2026&month=1')->assertForbidden();

    Sanctum::actingAs(User::factory()->manager()->inactive()->create());
    $this->getJson('/api/v1/manager/finance/monthly-summary?year=2026&month=1')->assertForbidden();
});
