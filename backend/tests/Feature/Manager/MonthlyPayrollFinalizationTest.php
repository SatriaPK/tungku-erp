<?php

use App\Actions\Finance\FinalizeMonthlyPayroll;
use App\Enum\PaymentStatus;
use App\Enum\PaymentType;
use App\Enum\PayrollStatus;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-10 12:00:00', 'Asia/Jakarta'));
    Sanctum::actingAs(User::factory()->manager()->create());
});

afterEach(function () {
    Carbon::setTestNow();
});

function finalizationPayment(string $status, ?string $paidAt, int $amount): Payment
{
    return Payment::factory()->create([
        'project_id' => Project::factory()->create()->id,
        'type' => PaymentType::Po,
        'status' => $status,
        'paid_at' => $paidAt,
        'amount' => $amount,
    ]);
}

it('snapshots a past month and calculates integer payroll amounts from selected percentages', function () {
    $manager = auth()->user();
    $operator = User::factory()->operator()->create();
    $payroll = Payroll::create([
        'period_year' => 2026,
        'period_month' => 10,
        'status' => PayrollStatus::Draft,
    ]);
    PayrollItem::create([
        'payroll_id' => $payroll->id,
        'operator_id' => $operator->id,
        'jobs_count' => 2,
        'recommended_percentage' => 100,
        'percentage' => 50,
        'amount' => 0,
    ]);
    finalizationPayment(PaymentStatus::Paid->value, '2026-10-15 00:00:00', 1001);
    $category = ExpenseCategory::create(['name' => 'Sewa', 'is_active' => true]);
    Expense::create([
        'expense_category_id' => $category->id,
        'recorded_by' => $manager->id,
        'description' => 'Biaya sewa',
        'amount' => 200,
        'spent_on' => '2026-10-15',
    ]);

    $this->postJson('/api/v1/manager/finance/payroll-finalization', ['year' => 2026, 'month' => 10])
        ->assertOk()
        ->assertJsonPath('data.status', 'finalized')
        ->assertJsonPath('data.allocations.0.percentage', '50');

    $payroll->refresh();
    expect($payroll->total_revenue)->toBe(1001)
        ->and($payroll->total_expense)->toBe(200)
        ->and($payroll->net_profit)->toBe(801)
        ->and($payroll->finalized_by)->toBe($manager->id)
        ->and($payroll->finalized_at)->not->toBeNull();
    expect((float) $payroll->studio_profit_percentage)->toBe(50.0)
        ->and($payroll->studio_profit_amount)->toBe(400);
    expect($payroll->items()->firstOrFail()->amount)->toBe(400);
    $this->assertDatabaseHas('payrolls', ['id' => $payroll->id, 'studio_profit_amount' => 400]);
});

it('retries finalization without changing the original snapshot or finalizer', function () {
    $firstManager = auth()->user();
    finalizationPayment(PaymentStatus::Paid->value, '2026-10-15 00:00:00', 500);
    $this->postJson('/api/v1/manager/finance/payroll-finalization', ['year' => 2026, 'month' => 10])->assertOk();

    $payroll = Payroll::query()->where('period_year', 2026)->where('period_month', 10)->firstOrFail();
    $finalizedAt = $payroll->finalized_at->toISOString();
    $secondManager = User::factory()->manager()->create();
    Sanctum::actingAs($secondManager);
    finalizationPayment(PaymentStatus::Paid->value, '2026-10-20 00:00:00', 9000);

    $this->postJson('/api/v1/manager/finance/payroll-finalization', ['year' => 2026, 'month' => 10])->assertOk();
    expect($payroll->fresh()->total_revenue)->toBe(500)
        ->and($payroll->fresh()->finalized_by)->toBe($firstManager->id)
        ->and($payroll->fresh()->finalized_at->toISOString())->toBe($finalizedAt);
});

it('rolls back payroll and item writes when finalization fails after updating an item', function () {
    $manager = auth()->user();
    $payroll = Payroll::create([
        'period_year' => 2026,
        'period_month' => 10,
        'status' => PayrollStatus::Draft,
    ]);
    $item = PayrollItem::create([
        'payroll_id' => $payroll->id,
        'operator_id' => User::factory()->operator()->create()->id,
        'jobs_count' => 1,
        'recommended_percentage' => 100,
        'percentage' => 50,
        'amount' => 0,
    ]);
    finalizationPayment(PaymentStatus::Paid->value, '2026-10-15 00:00:00', 1000);

    PayrollItem::updated(function (): void {
        throw new RuntimeException('Injected failure after payroll item update.');
    });

    expect(fn () => app(FinalizeMonthlyPayroll::class)->handle(2026, 10, $manager))
        ->toThrow(RuntimeException::class, 'Injected failure after payroll item update.');

    $payroll->refresh();
    $item->refresh();

    expect($payroll->getRawOriginal('status'))->toBe(PayrollStatus::Draft->value)
        ->and($payroll->finalized_by)->toBeNull()
        ->and($payroll->finalized_at)->toBeNull()
        ->and($payroll->total_revenue)->toBe(0)
        ->and($payroll->total_expense)->toBe(0)
        ->and($payroll->net_profit)->toBe(0)
        ->and($item->amount)->toBe(0);
});

it('rejects current and future periods without creating a payroll snapshot', function () {
    foreach ([[2026, 11], [2026, 12]] as [$year, $month]) {
        $this->postJson('/api/v1/manager/finance/payroll-finalization', ['year' => $year, 'month' => $month])
            ->assertUnprocessable()->assertJsonValidationErrors('period');
        $this->assertDatabaseMissing('payrolls', ['period_year' => $year, 'period_month' => $month]);
    }
});

it('rejects expense and allocation changes after finalization', function () {
    $category = ExpenseCategory::create(['name' => 'Listrik', 'is_active' => true]);
    $this->postJson('/api/v1/manager/finance/payroll-finalization', ['year' => 2026, 'month' => 10])->assertOk();

    $operator = User::factory()->operator()->create();
    $this->postJson('/api/v1/manager/expenses', [
        'expense_category_id' => $category->id,
        'description' => 'Tagihan Oktober',
        'amount' => 100,
        'spent_on' => '2026-10-31',
    ])->assertUnprocessable()->assertJsonValidationErrors('spent_on');

    $this->putJson('/api/v1/manager/finance/payroll-allocations', [
        'year' => 2026,
        'month' => 10,
        'allocations' => [['operator_id' => $operator->id, 'percentage' => '0']],
    ])->assertUnprocessable()->assertJsonValidationErrors('period');
});

it('validates the period and restricts finalization to active managers', function () {
    $this->postJson('/api/v1/manager/finance/payroll-finalization', ['year' => 2026, 'month' => 13])
        ->assertUnprocessable()->assertJsonValidationErrors('month');

    Sanctum::actingAs(User::factory()->operator()->create());
    $this->postJson('/api/v1/manager/finance/payroll-finalization', ['year' => 2026, 'month' => 10])->assertForbidden();

    Sanctum::actingAs(User::factory()->manager()->inactive()->create());
    $this->postJson('/api/v1/manager/finance/payroll-finalization', ['year' => 2026, 'month' => 10])->assertForbidden();
});
