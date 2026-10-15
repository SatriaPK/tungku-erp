<?php

use App\Actions\Finance\GetManagerDashboardMetrics;
use App\Enum\PaymentStatus;
use App\Enum\PaymentType;
use App\Enum\ProjectStatus;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Carbon;

function dashboardFixtureProject(ProjectStatus $status, int $price = 1000, int $poAmount = 500, ?int $packageId = null): Project
{
    $package = $packageId === null
        ? Package::factory()->create()
        : Package::query()->findOrFail($packageId);

    return Project::factory()->create([
        'status' => $status,
        'price' => $price,
        'po_amount' => $poAmount,
        'package_id' => $package->id,
        'package_snapshot' => ['name' => $package->name],
    ]);
}

function dashboardFixturePayment(Project $project, PaymentType $type, PaymentStatus $status, int $amount, ?string $paidAt): Payment
{
    return Payment::factory()->create([
        'project_id' => $project->id,
        'invoice_id' => null,
        'type' => $type,
        'status' => $status,
        'amount' => $amount,
        'paid_at' => $paidAt,
    ]);
}

it('calculates monthly finance, outstanding balances, active projects and package rankings', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-14 12:00:00', 'Asia/Jakarta'));
    $manager = User::factory()->manager()->create();

    $eligibleProject = dashboardFixtureProject(ProjectStatus::InProgress, price: 5000, poAmount: 500);
    $eligibleProject->update(['package_snapshot' => ['name' => 'Eligible package']]);
    dashboardFixturePayment($eligibleProject, PaymentType::Po, PaymentStatus::Paid, 500, '2026-10-02 10:00:00');
    dashboardFixturePayment($eligibleProject, PaymentType::Final, PaymentStatus::Paid, 1500, '2026-10-03 10:00:00');
    $eligibleProject->tracks()->createMany([
        ['title' => 'Track A', 'position' => 1],
        ['title' => 'Track B', 'position' => 2],
    ]);

    $unpaidPoProject = dashboardFixtureProject(ProjectStatus::InProgress, price: 9000, poAmount: 500);
    dashboardFixturePayment($unpaidPoProject, PaymentType::Po, PaymentStatus::Paid, 499, '2026-10-04 10:00:00');

    $cancelledProject = dashboardFixtureProject(ProjectStatus::Cancelled, price: 3000, poAmount: 500);
    dashboardFixturePayment($cancelledProject, PaymentType::Po, PaymentStatus::Paid, 500, '2026-10-05 10:00:00');

    $package = Package::factory()->create(['name' => 'Catalog name after project snapshot']);
    foreach (range(1, 2) as $position) {
        $project = Project::factory()->create([
            'status' => ProjectStatus::AwaitingAssignment,
            'price' => 1000,
            'po_amount' => 500,
            'package_id' => $package->id,
            'package_snapshot' => ['name' => 'Snapshot package'],
        ]);
        dashboardFixturePayment($project, PaymentType::Po, PaymentStatus::Paid, 500, "2026-10-0{$position} 10:00:00");
    }

    $category = ExpenseCategory::create(['name' => 'Studio', 'is_active' => true]);
    foreach ([['2026-09-10', 1000], ['2026-10-01', 100], ['2026-10-31', 200], ['2026-11-01', 900]] as [$spentOn, $amount]) {
        Expense::create([
            'expense_category_id' => $category->id,
            'recorded_by' => $manager->id,
            'description' => 'Biaya operasional',
            'amount' => $amount,
            'spent_on' => $spentOn,
        ]);
    }

    $metrics = app(GetManagerDashboardMetrics::class)->handle();

    expect($metrics['as_of'])->toBe('2026-10-14T12:00:00+07:00')
        ->and($metrics['period'])->toBe([
            'year' => 2026, 'month' => 10, 'timezone' => 'Asia/Jakarta',
            'today' => '2026-10-14', 'month_start' => '2026-10-01T00:00:00+07:00',
            'next_month_start' => '2026-11-01T00:00:00+07:00',
        ])
        ->and($metrics['revenue']['current'])->toBe(3999)
        ->and($metrics['revenue']['previous'])->toBe(0)
        ->and($metrics['revenue']['percentage'])->toBeNull()
        ->and($metrics['revenue']['direction'])->toBe('up')
        ->and($metrics['expense']['current'])->toBe(300)
        ->and($metrics['expense']['previous'])->toBe(1000)
        ->and($metrics['expense']['percentage'])->toBe(-70.0)
        ->and($metrics['monthly'])->toHaveCount(12)
        ->and($metrics['monthly'][8])->toBe(['month' => 9, 'revenue' => 0, 'expense' => 1000])
        ->and($metrics['monthly'][9])->toBe(['month' => 10, 'revenue' => 3999, 'expense' => 300])
        ->and($metrics['monthly'][10])->toBe(['month' => 11, 'revenue' => 0, 'expense' => 900])
        ->and($metrics['awaiting_final_payment'])->toBe(['amount' => 0, 'project_count' => 0, 'positive_balance_project_count' => 0])
        ->and($metrics['in_progress_projects'])->toBe(['count' => 2, 'track_count' => 2])
        ->and($metrics['package_sales']['total_count'])->toBe(4)
        ->and($metrics['package_sales']['items'])->toHaveCount(3)
        ->and($metrics['package_sales']['items'][0]['package_id'])->toBe($package->id)
        ->and($metrics['package_sales']['items'][0]['project_count'])->toBe(2)
        ->and($metrics['package_sales']['items'][0]['percentage'])->toBe(50.0)
        ->and($metrics['package_sales']['items'][1]['package_id'])->toBe($eligibleProject->package_id)
        ->and($metrics['package_sales']['items'][1]['project_count'])->toBe(1)
        ->and($metrics['package_sales']['items'][1]['percentage'])->toBe(25.0)
        ->and($metrics['package_sales']['items'][2]['package_id'])->toBe($unpaidPoProject->package_id)
        ->and(array_sum(array_column($metrics['package_sales']['items'], 'project_count')))->toBe(4);
});

it('returns empty aggregates and avoids division by zero for an empty dashboard', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-15 12:00:00', 'Asia/Jakarta'));

    $metrics = app(GetManagerDashboardMetrics::class)->handle();

    expect($metrics['revenue'])->toMatchArray([
        'current' => 0,
        'previous' => 0,
        'delta' => 0,
        'percentage' => null,
        'percentage_reason' => 'previous_not_positive',
        'direction' => 'unchanged',
    ])
        ->and($metrics['monthly'])->toHaveCount(12)
        ->and($metrics['awaiting_final_payment'])->toBe(['amount' => 0, 'project_count' => 0, 'positive_balance_project_count' => 0])
        ->and($metrics['in_progress_projects'])->toBe(['count' => 0, 'track_count' => 0])
        ->and($metrics['package_sales'])->toBe(['total_count' => 0, 'items' => []]);
});
