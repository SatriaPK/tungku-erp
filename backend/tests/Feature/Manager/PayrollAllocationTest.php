<?php

use App\Enum\PayrollStatus;
use App\Enum\ProjectStatus;
use App\Enum\StageStatus;
use App\Enum\TrackProgressStatus;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\ProjectTrack;
use App\Models\StudioSetting;
use App\Models\TrackStageProgress;
use App\Models\User;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Sanctum::actingAs(User::factory()->manager()->create());
});

function approvedPayrollJob(User $operator, string $approvedAt, string $status = 'approved'): void
{
    $completedAt = CarbonImmutable::parse($approvedAt)->addDays(1)->toDateTimeString();
    $project = Project::factory()->create([
        'status' => ProjectStatus::Completed,
        'completed_at' => $completedAt,
        'package_snapshot' => ['stages' => ['editing']],
    ]);
    $stage = ProjectStage::query()->create([
        'project_id' => $project->id,
        'stage' => 'editing',
        'sequence' => 1,
        'operator_id' => $operator->id,
        'status' => StageStatus::InProgress,
    ]);
    $track = ProjectTrack::query()->create(['project_id' => $project->id, 'title' => 'Track', 'position' => 1]);

    TrackStageProgress::query()->create([
        'project_stage_id' => $stage->id,
        'project_track_id' => $track->id,
        'status' => $status,
        'approved_at' => $approvedAt,
    ]);
}

it('recommends by completed projects in the Jakarta month and safely handles operators without jobs', function () {
    $first = User::factory()->operator()->create();
    $second = User::factory()->operator()->create();
    $idle = User::factory()->operator()->create();

    approvedPayrollJob($idle, '2025-12-30 23:59:59');
    approvedPayrollJob($first, '2025-12-31 00:00:00');
    approvedPayrollJob($first, '2026-01-30 23:59:59');
    approvedPayrollJob($idle, '2026-01-31 00:00:00');
    approvedPayrollJob($first, '2026-01-14 00:00:00', TrackProgressStatus::InReview->value);
    approvedPayrollJob($second, '2026-01-14 00:00:00');

    $this->getJson('/api/v1/manager/finance/payroll-allocations?year=2026&month=1')
        ->assertOk()
        ->assertJsonPath('data.period.timezone', 'Asia/Jakarta')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonCount(2, 'data.allocations')
        ->assertJsonPath('data.allocations.0.operator_id', $first->id)
        ->assertJsonPath('data.allocations.0.jobs_count', 2)
        ->assertJsonPath('data.allocations.0.recommended_percentage', '66.67')
        ->assertJsonPath('data.allocations.1.operator_id', $second->id)
        ->assertJsonPath('data.allocations.1.recommended_percentage', '33.33');

    $this->assertDatabaseMissing('payroll_items', ['operator_id' => $idle->id]);
});

it('stores complete draft allocations with exact decimal totals and leaves amounts for finalization', function () {
    $first = User::factory()->operator()->create();
    $second = User::factory()->operator()->create();
    approvedPayrollJob($first, '2026-01-15 00:00:00');
    approvedPayrollJob($second, '2026-01-15 00:00:00');

    $payload = [
        'year' => 2026,
        'month' => 1,
        'allocations' => [
            ['operator_id' => $first->id, 'percentage' => '23.33'],
            ['operator_id' => $second->id, 'percentage' => '26.67'],
        ],
    ];

    $this->putJson('/api/v1/manager/finance/payroll-allocations', $payload)
        ->assertOk()
        ->assertJsonPath('data.allocations.0.percentage', '23.33')
        ->assertJsonPath('data.allocations.1.percentage', '26.67');

    $payroll = Payroll::query()->where('period_year', 2026)->where('period_month', 1)->firstOrFail();
    expect($payroll->status)->toBe(PayrollStatus::Draft)
        ->and($payroll->total_revenue)->toBe(0)
        ->and($payroll->total_expense)->toBe(0)
        ->and($payroll->net_profit)->toBe(0);

    $this->assertSame(50, (int) $payroll->getRawOriginal('studio_profit_percentage'));

    $this->assertDatabaseHas('payroll_items', [
        'payroll_id' => $payroll->id,
        'operator_id' => $first->id,
        'jobs_count' => 1,
        'recommended_percentage' => 50,
        'percentage' => 23.33,
        'amount' => 0,
    ]);

    $this->putJson('/api/v1/manager/finance/payroll-allocations', [
        ...$payload,
        'allocations' => [
            ['operator_id' => $first->id, 'percentage' => '0.00'],
            ['operator_id' => $second->id, 'percentage' => '0'],
        ],
    ])->assertOk();

    $this->assertDatabaseCount('payroll_items', 2);
    expect(PayrollItem::query()->where('payroll_id', $payroll->id)->sum('amount'))->toBe(0);
});

it('copies the configured studio percentage into a draft and permits a period override', function () {
    StudioSetting::query()->where('key', 'studio_profit_percentage')->update(['value' => '30']);
    $first = User::factory()->operator()->create();
    approvedPayrollJob($first, '2026-01-15 00:00:00');

    $this->putJson('/api/v1/manager/finance/payroll-allocations', [
        'year' => 2026,
        'month' => 1,
        'studio_profit_percentage' => '40',
        'allocations' => [['operator_id' => $first->id, 'percentage' => '60']],
    ])->assertOk()
        ->assertJsonPath('data.studio_profit_percentage', '40.00');

    StudioSetting::query()->where('key', 'studio_profit_percentage')->update(['value' => '20']);
    $this->getJson('/api/v1/manager/finance/payroll-allocations?year=2026&month=1')
        ->assertOk()->assertJsonPath('data.studio_profit_percentage', '40.00');
});

it('rejects invalid totals precision duplicate or incomplete operators and final payroll mutations', function () {
    $first = User::factory()->operator()->create();
    $second = User::factory()->operator()->create();
    approvedPayrollJob($first, '2026-01-15 00:00:00');
    approvedPayrollJob($second, '2026-01-15 00:00:00');

    $base = [
        'year' => 2026,
        'month' => 1,
        'allocations' => [
            ['operator_id' => $first->id, 'percentage' => '25.00'],
            ['operator_id' => $second->id, 'percentage' => '25.00'],
        ],
    ];

    $this->putJson('/api/v1/manager/finance/payroll-allocations', [...$base, 'allocations' => [
        ['operator_id' => $first->id, 'percentage' => '50.01'],
        ['operator_id' => $second->id, 'percentage' => '50.00'],
    ]])->assertUnprocessable()->assertJsonValidationErrors('allocations');
    $this->putJson('/api/v1/manager/finance/payroll-allocations', [...$base, 'allocations' => [
        ['operator_id' => $first->id, 'percentage' => '50.001'],
        ['operator_id' => $second->id, 'percentage' => '49.999'],
    ]])->assertUnprocessable()->assertJsonValidationErrors('allocations.0.percentage');
    $this->putJson('/api/v1/manager/finance/payroll-allocations', [...$base, 'allocations' => [
        ['operator_id' => $first->id, 'percentage' => '50'],
        ['operator_id' => $first->id, 'percentage' => '50'],
    ]])->assertUnprocessable();
    $this->putJson('/api/v1/manager/finance/payroll-allocations', [...$base, 'allocations' => [
        ['operator_id' => $first->id, 'percentage' => '100'],
    ]])->assertUnprocessable()->assertJsonValidationErrors('allocations');
    $this->assertDatabaseMissing('payrolls', ['period_year' => 2026, 'period_month' => 1]);

    $this->putJson('/api/v1/manager/finance/payroll-allocations', $base)->assertOk();
    Payroll::query()->where('period_year', 2026)->where('period_month', 1)->update(['status' => PayrollStatus::Finalized]);

    $this->putJson('/api/v1/manager/finance/payroll-allocations', $base)
        ->assertUnprocessable()->assertJsonValidationErrors('period');
});

it('validates period and restricts allocation endpoints to active managers', function () {
    $this->getJson('/api/v1/manager/finance/payroll-allocations?year=2026&month=13')
        ->assertUnprocessable()->assertJsonValidationErrors('month');

    Sanctum::actingAs(User::factory()->operator()->create());
    $this->getJson('/api/v1/manager/finance/payroll-allocations?year=2026&month=1')->assertForbidden();
    $this->putJson('/api/v1/manager/finance/payroll-allocations', [])->assertForbidden();

    Sanctum::actingAs(User::factory()->manager()->inactive()->create());
    $this->getJson('/api/v1/manager/finance/payroll-allocations?year=2026&month=1')->assertForbidden();
});
