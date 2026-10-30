<?php

use App\Actions\Finance\GetManagerDashboardMetrics;
use App\Enum\PaymentStatus;
use App\Enum\PaymentType;
use App\Enum\PayrollStatus;
use App\Enum\ProjectStatus;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\Payroll;
use App\Models\Project;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

function dashboardFinanceExpense(string $spentOn, int $amount): Expense
{
    return Expense::create([
        'expense_category_id' => ExpenseCategory::firstOrCreate(['name' => 'Operasional'], ['is_active' => true])->id,
        'recorded_by' => User::factory()->manager()->create()->id,
        'description' => 'Biaya studio',
        'spent_on' => $spentOn,
        'amount' => $amount,
    ]);
}

it('uses one immutable reference time normalized to the configured timezone', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-14 12:00:00', 'Asia/Jakarta'));
    $asOf = CarbonImmutable::parse('2025-12-31 17:30:00', 'UTC');

    $metrics = app(GetManagerDashboardMetrics::class)->handle($asOf);

    expect($metrics['as_of'])->toBe('2026-01-01T00:30:00+07:00')
        ->and($metrics['period'])->toBe([
            'year' => 2026, 'month' => 1, 'timezone' => 'Asia/Jakarta',
            'today' => '2026-01-01', 'month_start' => '2026-01-01T00:00:00+07:00',
            'next_month_start' => '2026-02-01T00:00:00+07:00',
        ])
        ->and($asOf->toIso8601String())->toBe('2025-12-31T17:30:00+00:00')
        ->and($metrics['annual_totals'])->toBe(['revenue' => 0, 'expense' => 0]);

    foreach (range(1, 12) as $month) {
        expect($metrics['monthly'][$month - 1])->toBe(['month' => $month, 'revenue' => 0, 'expense' => 0]);
    }

    expect(app(GetManagerDashboardMetrics::class)->handle($asOf->setTimezone('Asia/Jakarta')))->toBe($metrics);
});

it('can return monthly financial chart totals for a selected calendar year', function () {
    dashboardFinanceExpense('2025-03-10', 250);
    dashboardFinanceExpense('2026-03-10', 500);
    Payment::factory()->paid()->create(['project_id' => null, 'paid_at' => '2025-03-10', 'amount' => 750]);
    Payment::factory()->paid()->create(['project_id' => null, 'paid_at' => '2026-03-10', 'amount' => 1500]);

    $metrics = app(GetManagerDashboardMetrics::class)->handle(null, 2025);

    expect($metrics['period']['year'])->toBe(2025)
        ->and($metrics['annual_totals'])->toBe(['revenue' => 750, 'expense' => 250])
        ->and($metrics['monthly'][2])->toBe(['month' => 3, 'revenue' => 750, 'expense' => 250]);
});

it('respects local month boundaries and the entire calendar year', function (string $asOf, array $dates, int $annualTotal) {
    $this->travelTo(CarbonImmutable::parse($asOf, 'Asia/Jakarta'));
    foreach ($dates as $date => $amount) {
        Payment::factory()->paid()->create(['project_id' => null, 'paid_at' => $date, 'amount' => $amount]);
        dashboardFinanceExpense(substr($date, 0, 10), $amount);
    }

    $metrics = app(GetManagerDashboardMetrics::class)->handle();

    expect($metrics['revenue']['previous'])->toBe(30)
        ->and($metrics['revenue']['current'])->toBe(300)
        ->and($metrics['expense']['previous'])->toBe(30)
        ->and($metrics['expense']['current'])->toBe(300)
        ->and($metrics['monthly'])->toHaveCount(12)
        ->and(array_column($metrics['monthly'], 'month'))->toBe(range(1, 12))
        ->and($metrics['annual_totals'])->toBe(['revenue' => $annualTotal, 'expense' => $annualTotal])
        ->and(array_sum(array_column($metrics['monthly'], 'revenue')))->toBe($annualTotal)
        ->and(array_sum(array_column($metrics['monthly'], 'expense')))->toBe($annualTotal);
})->with([
    'January reads previous December but excludes it from annual totals' => ['2026-01-15', [
        '2025-11-30 23:59:59' => 1, '2025-12-01 00:00:00' => 10, '2025-12-31 23:59:59' => 20,
        '2026-01-01 00:00:00' => 100, '2026-01-31 23:59:59' => 200,
        '2026-02-01 00:00:00' => 1000, '2026-12-31 23:59:59' => 2000, '2027-01-01 00:00:00' => 4000,
    ], 3300],
    'leap February includes the last second of February 29' => ['2024-02-15', [
        '2023-12-31 23:59:59' => 1, '2024-01-01 00:00:00' => 10, '2024-01-31 23:59:59' => 20,
        '2024-02-01 00:00:00' => 100, '2024-02-29 23:59:59' => 200,
        '2024-03-01 00:00:00' => 1000, '2024-12-31 23:59:59' => 2000, '2025-01-01 00:00:00' => 4000,
    ], 3330],
    'December excludes the next January' => ['2026-12-15', [
        '2025-12-31 23:59:59' => 1, '2026-11-01 00:00:00' => 10, '2026-11-30 23:59:59' => 20,
        '2026-12-01 00:00:00' => 100, '2026-12-31 23:59:59' => 200, '2027-01-01 00:00:00' => 4000,
    ], 330],
]);

it('compares revenue and expenses with explicit zero and rounding behavior', function (int $previous, int $current, int $delta, ?float $percentage, string $direction) {
    $this->travelTo(CarbonImmutable::parse('2026-10-14', 'Asia/Jakarta'));
    foreach (['2026-09-01' => $previous, '2026-10-01' => $current] as $date => $amount) {
        Payment::factory()->paid()->create(['project_id' => null, 'paid_at' => $date, 'amount' => $amount]);
        dashboardFinanceExpense($date, $amount);
    }

    $metrics = app(GetManagerDashboardMetrics::class)->handle();
    foreach (['revenue', 'expense'] as $field) {
        expect($metrics[$field])->toBe([
            'current' => $current, 'previous' => $previous, 'delta' => $delta,
            'percentage' => $percentage, 'percentage_reason' => $previous === 0 ? 'previous_not_positive' : null,
            'direction' => $direction,
        ]);
    }
})->with([
    'increase' => [100, 150, 50, 50.0, 'up'],
    'decrease to zero' => [100, 0, -100, -100.0, 'down'],
    'both zero' => [0, 0, 0, null, 'unchanged'],
    'no positive baseline' => [0, 150, 150, null, 'up'],
    'rounding' => [3, 4, 1, 33.33, 'up'],
    'unchanged positive' => [100, 100, 0, 0.0, 'unchanged'],
]);

it('counts only dated paid payments including PO and final from terminal projects', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-14', 'Asia/Jakarta'));
    foreach ([ProjectStatus::Completed, ProjectStatus::Cancelled] as $status) {
        $project = Project::factory()->create(['status' => $status]);
        $project->tracks()->createMany([['title' => 'A', 'position' => 1], ['title' => 'B', 'position' => 2]]);
        foreach ([PaymentType::Po, PaymentType::Final] as $type) {
            Payment::factory()->paid()->create(['project_id' => $project->id, 'type' => $type, 'amount' => 100, 'paid_at' => '2026-10-01']);
        }
    }
    foreach (PaymentStatus::cases() as $status) {
        Payment::factory()->create([
            'project_id' => null, 'status' => $status, 'amount' => 9000,
            'paid_at' => $status === PaymentStatus::Paid ? null : '2026-10-01',
        ]);
    }

    $metrics = app(GetManagerDashboardMetrics::class)->handle();

    expect($metrics['revenue']['current'])->toBe(400)
        ->and($metrics['annual_totals']['revenue'])->toBe(400);
});

it('reads live finance without changing paid timestamps or finalized snapshots', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-14', 'Asia/Jakarta'));
    $payroll = Payroll::create([
        'period_year' => 2026, 'period_month' => 10, 'total_revenue' => 5000,
        'total_expense' => 2000, 'net_profit' => 3000, 'status' => PayrollStatus::Finalized,
    ]);
    $payment = Payment::factory()->paid()->create(['project_id' => null, 'amount' => 3000000000, 'paid_at' => '2026-10-01']);
    dashboardFinanceExpense('2026-10-01', 70)->update(['created_at' => '2025-01-01']);
    dashboardFinanceExpense('2026-09-01', 20);
    $snapshot = $payroll->refresh()->getRawOriginal();
    $paymentSnapshot = $payment->refresh()->getRawOriginal();

    DB::enableQueryLog();
    try {
        $metrics = app(GetManagerDashboardMetrics::class)->handle();
        $queries = DB::getQueryLog();
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }

    expect($metrics['revenue']['current'])->toBe(3000000000)
        ->and($metrics['expense']['current'])->toBe(70)
        ->and($metrics['expense']['previous'])->toBe(20)
        ->and($payroll->refresh()->getRawOriginal())->toBe($snapshot)
        ->and($payment->refresh()->getRawOriginal())->toBe($paymentSnapshot);
    foreach ($queries as $query) {
        expect(strtolower(ltrim($query['query'])))->toStartWith('select');
    }
});
