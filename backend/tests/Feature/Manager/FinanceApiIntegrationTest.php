<?php

use App\Enum\PaymentStatus;
use App\Enum\PaymentType;
use App\Enum\PayrollStatus;
use App\Enum\ProjectStatus;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\ProjectTrack;
use App\Models\TrackStageProgress;
use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::parse('2026-11-10 12:00:00', 'Asia/Jakarta'));
    Sanctum::actingAs(User::factory()->manager()->create());
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function financeIntegrationPayment(string $status, ?string $paidAt, int $amount): Payment
{
    return Payment::factory()->create([
        'project_id' => Project::factory()->create()->id,
        'type' => PaymentType::Po,
        'status' => $status,
        'paid_at' => $paidAt,
        'amount' => $amount,
    ]);
}

function financeIntegrationApprovedJob(User $operator, string $approvedAt): void
{
    $project = Project::factory()->create([
        'status' => ProjectStatus::Completed,
        'completed_at' => Carbon::parse($approvedAt)->addDay()->toDateTimeString(),
        'package_snapshot' => ['stages' => ['editing']],
    ]);
    $stage = ProjectStage::query()->create([
        'project_id' => $project->id,
        'stage' => 'editing',
        'sequence' => 1,
        'operator_id' => $operator->id,
        'status' => 'in_progress',
    ]);
    $track = ProjectTrack::query()->create([
        'project_id' => $project->id,
        'title' => 'Track',
        'position' => 1,
    ]);

    TrackStageProgress::query()->create([
        'project_stage_id' => $stage->id,
        'project_track_id' => $track->id,
        'status' => 'approved',
        'approved_at' => $approvedAt,
    ]);
}

it('integrates category expense summary allocation finalization and immutable snapshot reads', function (): void {
    $manager = auth()->user();
    $operator = User::factory()->operator()->create();
    $categoryId = $this->postJson('/api/v1/manager/expense-categories', ['name' => 'Sewa studio'])
        ->assertCreated()
        ->json('data.id');
    $category = ExpenseCategory::query()->findOrFail($categoryId);

    financeIntegrationPayment(PaymentStatus::Paid->value, '2026-10-15 00:00:00', 1001);
    financeIntegrationPayment(PaymentStatus::Pending->value, '2026-10-15 00:00:00', 9000);
    financeIntegrationApprovedJob($operator, '2026-10-15 00:00:00');

    $expenseId = $this->postJson('/api/v1/manager/expenses', [
        'expense_category_id' => $category->id,
        'description' => 'Biaya sewa Oktober',
        'amount' => 200,
        'spent_on' => '2026-10-15',
        'recorded_by' => 999999,
        'status' => 'finalized',
        'amount_total' => 999999,
    ])->assertCreated()
        ->assertJsonPath('data.recorded_by', $manager->id)
        ->assertJsonMissingPath('data.status')
        ->json('data.id');

    $this->getJson('/api/v1/manager/finance/monthly-summary?year=2026&month=10')
        ->assertOk()
        ->assertJsonPath('data.totals.revenue', 1001)
        ->assertJsonPath('data.totals.expense', 200)
        ->assertJsonPath('data.totals.net_profit', 801)
        ->assertJsonPath('data.expenses_by_category.0.category_name', 'Sewa studio');

    $this->putJson('/api/v1/manager/finance/payroll-allocations', [
        'year' => 2026,
        'month' => 10,
        'allocations' => [['operator_id' => $operator->id, 'percentage' => '50']],
        'status' => 'finalized',
        'finalized_by' => 999999,
        'finalized_at' => '2026-10-31 23:59:59',
        'total_revenue' => 999999,
    ])->assertOk()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.allocations.0.percentage', '50');

    $this->postJson('/api/v1/manager/finance/payroll-finalization', [
        'year' => 2026,
        'month' => 10,
        'status' => 'draft',
        'finalized_by' => 999999,
        'finalized_at' => '2026-10-31 23:59:59',
        'total_revenue' => 999999,
    ])->assertOk()
        ->assertJsonPath('data.status', 'finalized')
        ->assertJsonPath('data.allocations.0.percentage', '50')
        ->assertJsonPath('data.allocations.0.operator_id', $operator->id);

    $this->assertDatabaseHas('expenses', [
        'id' => $expenseId,
        'recorded_by' => $manager->id,
        'amount' => 200,
    ]);
    $this->assertDatabaseHas('payrolls', [
        'period_year' => 2026,
        'period_month' => 10,
        'total_revenue' => 1001,
        'total_expense' => 200,
        'net_profit' => 801,
        'status' => PayrollStatus::Finalized->value,
        'finalized_by' => $manager->id,
    ]);
    $this->assertDatabaseHas('payroll_items', [
        'operator_id' => $operator->id,
        'percentage' => 50,
        'amount' => 400,
    ]);

    financeIntegrationPayment(PaymentStatus::Paid->value, '2026-10-20 00:00:00', 9000);

    $this->getJson('/api/v1/manager/finance/monthly-summary?year=2026&month=10')
        ->assertOk()
        ->assertJsonPath('data.totals.revenue', 1001)
        ->assertJsonPath('data.totals.expense', 200)
        ->assertJsonPath('data.totals.net_profit', 801)
        ->assertJsonPath('data.expenses_by_category', []);
    $this->getJson('/api/v1/manager/finance/payroll-allocations?year=2026&month=10')
        ->assertOk()
        ->assertJsonPath('data.status', 'finalized')
        ->assertJsonPath('data.allocations.0.percentage', '50')
        ->assertJsonPath('data.allocations.0.operator_id', $operator->id);

    $this->assertDatabaseCount('payrolls', 1);
    $this->assertDatabaseCount('payroll_items', 1);
});

it('rejects unauthorized and unauthenticated finance API requests', function (): void {
    $routes = [
        ['getJson', '/api/v1/manager/expense-categories'],
        ['getJson', '/api/v1/manager/expenses'],
        ['getJson', '/api/v1/manager/finance/monthly-summary?year=2026&month=10'],
        ['getJson', '/api/v1/manager/finance/payroll-allocations?year=2026&month=10'],
        ['postJson', '/api/v1/manager/finance/payroll-finalization'],
    ];

    Sanctum::actingAs(User::factory()->operator()->create());
    foreach ($routes as [$method, $uri]) {
        $this->{$method}($uri)->assertForbidden();
    }

    $this->app['auth']->forgetGuards();
    $this->getJson('/api/v1/manager/finance/monthly-summary?year=2026&month=10')->assertUnauthorized();
});

it('rejects cross-period allocation operators and invalid expense categories without changing stored data', function (): void {
    $unrelatedOperator = User::factory()->operator()->create();
    $activeCategory = ExpenseCategory::create(['name' => 'Peralatan', 'is_active' => true]);
    $inactiveCategory = ExpenseCategory::create(['name' => 'Lama', 'is_active' => false]);

    $this->postJson('/api/v1/manager/expenses', [
        'expense_category_id' => $inactiveCategory->id,
        'description' => 'Pembelian',
        'amount' => 100,
        'spent_on' => '2026-10-15',
    ])->assertUnprocessable()->assertJsonValidationErrors('expense_category_id');

    $this->putJson('/api/v1/manager/finance/payroll-allocations', [
        'year' => 2026,
        'month' => 10,
        'allocations' => [['operator_id' => $unrelatedOperator->id, 'percentage' => '100']],
    ])->assertUnprocessable()->assertJsonValidationErrors('allocations');

    $this->assertDatabaseCount('expenses', 0);
    $this->assertDatabaseCount('payrolls', 0);
    $this->assertDatabaseHas('expense_categories', ['id' => $activeCategory->id]);
});
