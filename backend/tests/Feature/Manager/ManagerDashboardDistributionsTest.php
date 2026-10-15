<?php

use App\Actions\Finance\GetManagerDashboardMetrics;
use App\Enum\PaymentStatus;
use App\Enum\PaymentType;
use App\Enum\ProjectStatus;
use App\Enum\QuotationStatus;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

it('counts every noncancelled project in its creation month independently of payments', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15', 'Asia/Jakarta'));
    $package = Package::factory()->create();
    foreach (ProjectStatus::cases() as $status) {
        $project = Project::factory()->create(['package_id' => $package->id, 'status' => $status]);
        Payment::factory()->paid()->create(['project_id' => $project->id, 'type' => PaymentType::Po, 'amount' => 0]);
        Payment::factory()->paid()->create(['project_id' => $project->id, 'type' => PaymentType::Final, 'amount' => 100]);
        $project->tracks()->createMany([['title' => 'A', 'position' => 1], ['title' => 'B', 'position' => 2]]);
    }
    foreach ([PaymentStatus::Pending, PaymentStatus::Failed] as $status) {
        $project = Project::factory()->create(['package_id' => $package->id]);
        Payment::factory()->create(['project_id' => $project->id, 'status' => $status]);
    }
    foreach (['2026-09-30 23:59:59', '2026-10-01 00:00:00', '2026-10-31 23:59:59', '2026-11-01 00:00:00'] as $date) {
        Project::factory()->create(['package_id' => $package->id, 'created_at' => $date]);
    }
    $september = Project::where('created_at', '2026-09-30 23:59:59')->firstOrFail();
    Payment::factory()->paid()->create(['project_id' => $september->id, 'paid_at' => '2026-10-02']);

    $metrics = app(GetManagerDashboardMetrics::class)->handle();

    expect($metrics['package_sales']['total_count'])->toBe(9)
        ->and($metrics['package_sales']['items'])->toHaveCount(1)
        ->and($metrics['package_sales']['items'][0]['project_count'])->toBe(9)
        ->and($metrics['package_sales']['items'][0]['percentage'])->toBe(100.0)
        ->and(app(GetManagerDashboardMetrics::class)->handle(CarbonImmutable::parse('2026-09-15', 'Asia/Jakarta'))['package_sales']['total_count'])->toBe(1);
});

it('returns all groups with earliest snapshots and whole-month percentages using bounded queries', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15', 'Asia/Jakarta'));
    $packages = Package::factory()->count(5)->create();
    foreach ($packages as $package) {
        Project::factory()->create(['package_id' => $package->id, 'created_at' => '2026-10-03', 'package_snapshot' => ['name' => 'Later']]);
    }
    Project::factory()->create(['package_id' => $packages[0]->id, 'created_at' => '2026-10-01', 'package_snapshot' => ['name' => 'Original']]);
    Project::factory()->create(['package_id' => $packages[0]->id, 'created_at' => '2026-10-01', 'package_snapshot' => ['name' => 'Same date later ID']]);
    Project::factory()->create(['package_id' => $packages[0]->id, 'status' => ProjectStatus::Cancelled, 'created_at' => '2026-10-01', 'package_snapshot' => ['name' => 'Cancelled']]);
    $packages[0]->update(['name' => 'Renamed catalog']);
    $packages[1]->delete();

    DB::enableQueryLog();
    try {
        $metrics = app(GetManagerDashboardMetrics::class)->handle();
        $queries = DB::getQueryLog();
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }

    $sales = $metrics['package_sales'];
    expect($sales['total_count'])->toBe(7)
        ->and($sales['items'])->toHaveCount(5)
        ->and(array_sum(array_column($sales['items'], 'project_count')))->toBe(7)
        ->and($sales['items'][0])->toBe([
            'key' => 'package:'.$packages[0]->id, 'package_id' => $packages[0]->id, 'quotation_id' => null,
            'name' => 'Original', 'project_count' => 3, 'percentage' => 42.86,
        ])
        ->and(array_column(array_slice($sales['items'], 1), 'percentage'))->toBe([14.29, 14.29, 14.29, 14.29])
        ->and(array_column($sales['items'], 'package_id'))->toBe($packages->pluck('id')->all())
        ->and(count($queries))->toBeLessThanOrEqual(7);
    foreach ($queries as $query) {
        expect(strtolower(ltrim($query['query'])))->toStartWith('select');
    }
});

it('preserves custom identities and honest fallback labels for legacy and deleted records', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15', 'Asia/Jakarta'));
    $quotation = Quotation::create(['client_id' => User::factory()->client()->create()->id, 'track_count' => 1, 'recording_hours' => 1, 'auto_price' => 100, 'status' => QuotationStatus::Accepted]);
    Project::factory()->create(['package_id' => null, 'quotation_id' => $quotation->id, 'package_snapshot' => ['quotation_id' => 777, 'name' => 'Custom name'], 'created_at' => '2026-10-01']);
    Project::factory()->create(['package_id' => null, 'package_snapshot' => ['quotation_id' => $quotation->id, 'name' => 'Later name']]);
    Project::factory()->create(['package_id' => null, 'package_snapshot' => ['quotation_id' => 888, 'name' => '  ']]);
    Project::factory()->create(['package_id' => null, 'package_snapshot' => ['quotation_id' => 0, 'name' => 'Unknown identity']]);
    $deleted = Package::factory()->create();
    Project::factory()->create(['package_id' => $deleted->id, 'package_snapshot' => ['name' => 'Deleted package']]);
    $deleted->forceDelete();
    $package = Package::factory()->create();
    Project::factory()->create(['package_id' => $package->id, 'package_snapshot' => ['quotation_id' => 888]]);

    $sales = app(GetManagerDashboardMetrics::class)->handle()['package_sales'];
    $groups = array_column($sales['items'], null, 'key');
    expect($sales['total_count'])->toBe(6)
        ->and($groups)->toHaveCount(4)
        ->and($groups['quotation:'.$quotation->id]['name'])->toBe('Custom name')
        ->and($groups['quotation:'.$quotation->id]['project_count'])->toBe(2)
        ->and($groups['quotation:888']['name'])->toBe('Penawaran Kustom #888')
        ->and($groups['unavailable']['name'])->toBe('Paket tidak tersedia')
        ->and($groups['unavailable']['project_count'])->toBe(2)
        ->and($groups['package:'.$package->id]['name'])->toBe('Paket tanpa snapshot')
        ->and($groups['package:'.$package->id]['quotation_id'])->toBeNull();
});

it('includes all expense categories and reconciles amounts and transaction counts by spent date', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15', 'Asia/Jakarta'));
    $manager = User::factory()->manager()->create();
    $categories = [];
    foreach (range(1, 5) as $number) {
        $category = ExpenseCategory::create(['name' => 'Category '.$number, 'is_active' => $number !== 1]);
        $categories[] = $category->id;
        foreach (['2026-10-01', '2026-10-31'] as $date) {
            Expense::create(['expense_category_id' => $category->id, 'recorded_by' => $manager->id, 'description' => 'Expense', 'amount' => 50, 'spent_on' => $date, 'created_at' => '2025-01-01']);
        }
    }
    foreach (['2026-09-30', '2026-11-01'] as $date) {
        Expense::create(['expense_category_id' => $categories[0], 'recorded_by' => $manager->id, 'description' => 'Outside period', 'amount' => 9000, 'spent_on' => $date]);
    }
    $metrics = app(GetManagerDashboardMetrics::class)->handle();
    $distribution = $metrics['expense_categories'];
    expect($distribution['total_amount'])->toBe(500)
        ->and($distribution['total_amount'])->toBe($metrics['expense']['current'])
        ->and($distribution['transaction_count'])->toBe(10)
        ->and(array_column($distribution['items'], 'category_id'))->toBe($categories)
        ->and(array_column($distribution['items'], 'percentage'))->toBe([20.0, 20.0, 20.0, 20.0, 20.0])
        ->and(array_sum(array_column($distribution['items'], 'amount')))->toBe(500)
        ->and(array_sum(array_column($distribution['items'], 'transaction_count')))->toBe(10);
});

it('returns stable empty distributions and zero percentages for zero expenses', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15', 'Asia/Jakarta'));
    $metrics = app(GetManagerDashboardMetrics::class)->handle();
    expect($metrics['package_sales'])->toBe(['total_count' => 0, 'items' => []])
        ->and($metrics['expense_categories'])->toBe(['total_amount' => 0, 'transaction_count' => 0, 'items' => []]);
    $category = ExpenseCategory::create(['name' => 'Zero', 'is_active' => false]);
    Expense::create(['expense_category_id' => $category->id, 'recorded_by' => User::factory()->manager()->create()->id, 'description' => 'Zero', 'amount' => 0, 'spent_on' => '2026-10-01']);
    expect(app(GetManagerDashboardMetrics::class)->handle()['expense_categories'])->toBe([
        'total_amount' => 0, 'transaction_count' => 1,
        'items' => [['category_id' => $category->id, 'category_name' => 'Zero', 'transaction_count' => 1, 'amount' => 0, 'percentage' => 0.0]],
    ]);
});
