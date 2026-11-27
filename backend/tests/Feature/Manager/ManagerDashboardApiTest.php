<?php

use App\Actions\Project\ConfirmStageDeadline;
use App\Actions\Project\RecordOverdueStages;
use App\Actions\Project\SubmitTrackResult;
use App\Enum\OperatorActivityType;
use App\Enum\ProjectStatus;
use App\Enum\QuotationStatus;
use App\Enum\StageStatus;
use App\Enum\StageType;
use App\Enum\TrackProgressStatus;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\OperatorActivity;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\RecordingSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15 12:00:00', 'Asia/Jakarta'));
});

it('returns 401 to guests on both dashboard endpoints', function (string $path) {
    $this->getJson($path)->assertUnauthorized();
})->with(['/api/v1/manager/dashboard', '/api/v1/manager/dashboard/operator-activities']);

it('returns 403 to other roles and inactive managers', function (string $path, string $role, bool $active) {
    Sanctum::actingAs(User::factory()->create(['role' => $role, 'is_active' => $active]));

    $this->getJson($path)->assertForbidden();
})->with(['/api/v1/manager/dashboard', '/api/v1/manager/dashboard/operator-activities'])
    ->with(['client' => ['client', true], 'operator' => ['operator', true], 'inactive manager' => ['manager', false]]);

it('returns the complete empty dashboard contract using server time', function () {
    Sanctum::actingAs(User::factory()->manager()->create());

    $response = $this->getJson('/api/v1/manager/dashboard?month=1&as_of=2000-01-01')->assertOk();
    $response->assertJsonPath('data.as_of', '2026-10-15T12:00:00+07:00')
        ->assertJsonPath('data.period', ['year' => 2026, 'month' => 10, 'timezone' => 'Asia/Jakarta',
            'today' => '2026-10-15', 'month_start' => '2026-10-01T00:00:00+07:00', 'next_month_start' => '2026-11-01T00:00:00+07:00'])
        ->assertJsonPath('data.revenue', ['current' => 0, 'previous' => 0, 'delta' => 0, 'percentage' => null,
            'percentage_reason' => 'previous_not_positive', 'direction' => 'unchanged'])
        ->assertJsonPath('data.annual_totals', ['revenue' => 0, 'expense' => 0])
        ->assertJsonPath('data.awaiting_final_payment', ['amount' => 0, 'project_count' => 0, 'positive_balance_project_count' => 0])
        ->assertJsonPath('data.in_progress_projects', ['count' => 0, 'track_count' => 0])
        ->assertJsonPath('data.quotations', ['open_count' => 0, 'awaiting_studio_count' => 0])
        ->assertJsonPath('data.package_sales', ['total_count' => 0, 'items' => []])
        ->assertJsonPath('data.expense_categories', ['total_amount' => 0, 'transaction_count' => 0, 'items' => []])
        ->assertJsonCount(12, 'data.monthly')->assertJsonCount(4, 'data.tracks_by_stage')
        ->assertJsonPath('data.unclassified_track_count', 0)
        ->assertJsonPath('data.recording_schedule', [])->assertJsonPath('data.project_progress', [])
        ->assertJsonPath('data.quotation_progress', [])->assertJsonPath('data.operator_activities', []);
    expect(array_keys($response->json('data')))->toHaveCount(18);
    expect($response->json('data.expense'))->toBe($response->json('data.revenue'));
});

it('returns dashboard monthly financial data for the requested year', function () {
    Sanctum::actingAs(User::factory()->manager()->create());
    Payment::factory()->paid()->create(['project_id' => null, 'paid_at' => '2025-03-10', 'amount' => 750]);
    Expense::create(['expense_category_id' => ExpenseCategory::create(['name' => 'Operasional', 'is_active' => true])->id,
        'recorded_by' => User::factory()->manager()->create()->id, 'amount' => 250,
        'spent_on' => '2025-03-10', 'description' => 'Biaya studio']);

    $this->getJson('/api/v1/manager/dashboard?year=2025')->assertOk()
        ->assertJsonPath('data.period.year', 2025)
        ->assertJsonPath('data.annual_totals', ['revenue' => 750, 'expense' => 250])
        ->assertJsonPath('data.monthly.2', ['month' => 3, 'revenue' => 750, 'expense' => 250]);
});

it('returns an empty standalone feed', function () {
    Sanctum::actingAs(User::factory()->manager()->create());

    $this->getJson('/api/v1/manager/dashboard/operator-activities')->assertOk()->assertExactJson([
        'data' => ['as_of' => '2026-10-15T12:00:00+07:00', 'timezone' => 'Asia/Jakarta', 'items' => []],
    ]);
});

it('integrates all metrics and complete progress lists without exposing private fields', function () {
    $manager = User::factory()->manager()->create();
    Sanctum::actingAs($manager);
    $client = User::factory()->client()->create(['name' => 'Klien', 'email' => 'secret@example.com', 'phone' => '628123456789']);
    for ($index = 0; $index < 5; $index++) {
        $project = Project::factory()->create(['client_id' => $client->id, 'status' => ProjectStatus::InProgress,
            'package_snapshot' => ['name' => 'Paket '.$index], 'name' => 'Project '.$index]);
        $project->tracks()->create(['title' => 'Track', 'position' => 1]);
        $project->stages()->create(['stage' => StageType::Editing, 'sequence' => 1, 'status' => StageStatus::InProgress]);
        RecordingSession::factory()->confirmed()->create(['project_id' => $project->id, 'client_id' => $client->id,
            'start_at' => '2026-10-15 10:00:00', 'end_at' => '2026-10-15 11:00:00']);
        Quotation::create(['client_id' => $client->id, 'name' => $index === 0 ? null : 'Penawaran '.$index,
            'track_count' => 1, 'recording_hours' => 1, 'auto_price' => 100,
            'status' => $index === 0 ? QuotationStatus::AwaitingStudio : QuotationStatus::AwaitingClient]);
        $category = ExpenseCategory::create(['name' => 'Kategori '.$index, 'is_active' => true]);
        Expense::create(['expense_category_id' => $category->id, 'recorded_by' => $manager->id,
            'amount' => 10, 'spent_on' => '2026-10-15', 'description' => 'Private expense note']);
    }
    $awaiting = Project::factory()->create(['status' => ProjectStatus::AwaitingFinalPayment, 'price' => 1000, 'created_at' => '2026-09-01']);
    Payment::factory()->paid()->create(['project_id' => $awaiting->id, 'amount' => 400, 'paid_at' => '2026-10-15']);
    Payment::factory()->paid()->create(['project_id' => $project->id, 'amount' => 200, 'paid_at' => '2026-09-15']);
    OperatorActivity::factory()->count(5)->create(['occurred_at' => '2026-10-15 11:00:00']);

    $response = $this->getJson('/api/v1/manager/dashboard?limit=3&page=2')->assertOk()
        ->assertJsonPath('data.revenue.current', 400)->assertJsonPath('data.revenue.previous', 200)
        ->assertJsonPath('data.revenue.percentage', 100)->assertJsonPath('data.expense.current', 50)
        ->assertJsonPath('data.annual_totals', ['revenue' => 600, 'expense' => 50])
        ->assertJsonPath('data.monthly.9', ['month' => 10, 'revenue' => 400, 'expense' => 50])
        ->assertJsonPath('data.awaiting_final_payment', ['amount' => 600, 'project_count' => 1, 'positive_balance_project_count' => 1])
        ->assertJsonPath('data.in_progress_projects', ['count' => 5, 'track_count' => 5])
        ->assertJsonPath('data.tracks_by_stage.1.track_count', 5)
        ->assertJsonPath('data.quotations', ['open_count' => 5, 'awaiting_studio_count' => 1])
        ->assertJsonPath('data.package_sales.total_count', 5)->assertJsonCount(5, 'data.package_sales.items')
        ->assertJsonPath('data.expense_categories.total_amount', 50)->assertJsonCount(5, 'data.expense_categories.items')
        ->assertJsonCount(5, 'data.recording_schedule')->assertJsonCount(5, 'data.project_progress')
        ->assertJsonCount(5, 'data.quotation_progress')->assertJsonCount(5, 'data.operator_activities')
        ->assertJsonPath('data.project_progress.0.current_stage', 'editing')
        ->assertJsonPath('data.quotation_progress.0.package_display_name', 'Penawaran 4');
    expect($response->json('data.quotation_progress.4.package_display_name'))->toStartWith('Penawaran Kustom #');
    expect($response->getContent())->not->toContain('secret@example.com', '628123456789', 'result_url', 'Private expense note', 'remember_token');
});

it('returns truthful snapshots and occurrence ages for all event types in stable order', function () {
    Sanctum::actingAs(User::factory()->manager()->create());
    $operator = User::factory()->operator()->create(['name' => 'Nama baru']);
    $submitted = OperatorActivity::factory()->create(['type' => OperatorActivityType::ResultSubmitted,
        'actor_id' => $operator->id, 'operator_id' => $operator->id, 'actor_name' => 'Operator asli', 'operator_name' => 'Operator asli',
        'project_name' => 'Album', 'track_title' => 'Intro', 'deadline_at' => null, 'occurred_at' => '2026-10-15 11:58:00']);
    $deadline = OperatorActivity::factory()->create(['actor_name' => 'Operator asli', 'project_name' => 'Album', 'occurred_at' => '2026-10-15 11:59:00']);
    $overdue = OperatorActivity::factory()->create(['type' => OperatorActivityType::StageOverdue,
        'operator_name' => 'Pengganti', 'project_name' => 'Album', 'occurred_at' => '2026-10-15 11:59:00',
        'deadline_at' => '2026-10-15 10:00:00']);
    $boundary = OperatorActivity::factory()->create(['occurred_at' => now()]);
    OperatorActivity::factory()->create(['occurred_at' => now()->addSecond()]);
    $operator->delete();

    $response = $this->getJson('/api/v1/manager/dashboard/operator-activities?limit=1')->assertOk()->assertJsonCount(4, 'data.items');
    expect(array_column($response->json('data.items'), 'id'))->toBe([$boundary->id, $overdue->id, $deadline->id, $submitted->id]);
    $response->assertJsonPath('data.items.0.age_seconds', 0)
        ->assertJsonPath('data.items.1.age_seconds', 60)
        ->assertJsonPath('data.items.1.actor.is_system', true)
        ->assertJsonPath('data.items.1.deadline_at', '2026-10-15T10:00:00+07:00')
        ->assertJsonPath('data.items.1.description', 'Sistem mendeteksi tahap Editing pada project Album melewati tenggat; operator saat deteksi: Pengganti.')
        ->assertJsonPath('data.items.2.description', 'Operator asli menetapkan tenggat tahap Editing, pada project Album.')
        ->assertJsonPath('data.items.3.actor', ['id' => null, 'name' => 'Operator asli', 'is_system' => false])
        ->assertJsonPath('data.items.3.occurred_at', '2026-10-15T11:58:00+07:00')
        ->assertJsonPath('data.items.3.recorded_at', '2026-10-15T12:00:00+07:00')
        ->assertJsonPath('data.items.3.age_seconds', 120)
        ->assertJsonPath('data.items.3.description', 'Operator asli mengirim link hasil track Intro, dari project Album.');
    expect(array_keys($response->json('data.items.3')))->toBe(['id', 'type', 'actor', 'operator', 'project', 'stage', 'track',
        'occurred_at', 'recorded_at', 'deadline_at', 'age_seconds', 'description']);
    $this->getJson('/api/v1/manager/dashboard')->assertOk()->assertJsonPath('data.operator_activities', $response->json('data.items'));
});

it('keeps repeated reads read-only and feed queries constant as the ledger grows', function () {
    Sanctum::actingAs(User::factory()->manager()->create());
    $project = Project::factory()->create(['status' => ProjectStatus::InProgress]);
    $stage = $project->stages()->create(['stage' => StageType::Editing, 'sequence' => 1, 'status' => StageStatus::InProgress,
        'deadline_at' => '2026-10-14 10:00:00']);
    OperatorActivity::factory()->create(['source_id' => $stage->id, 'project_id' => $project->id,
        'project_stage_id' => $stage->id, 'deadline_at' => $stage->deadline_at, 'occurred_at' => '2026-10-13']);
    $before = $stage->refresh()->getRawOriginal();
    DB::enableQueryLog();
    try {
        $this->getJson('/api/v1/manager/dashboard')->assertOk();
        $this->getJson('/api/v1/manager/dashboard')->assertOk();
        $queries = DB::getQueryLog();
        expect(collect($queries)->filter(fn (array $query): bool => preg_match('/^\s*(insert|update|delete|replace)\b/i', $query['query']) === 1))->toHaveCount(0);
        DB::flushQueryLog();
        $this->getJson('/api/v1/manager/dashboard/operator-activities')->assertOk()->assertJsonCount(1, 'data.items');
        $smallCount = count(DB::getQueryLog());
        DB::disableQueryLog();
        OperatorActivity::factory()->count(204)->create();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/v1/manager/dashboard/operator-activities')->assertOk()->assertJsonCount(205, 'data.items');
        expect(count(DB::getQueryLog()))->toBe($smallCount);
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
    expect($stage->refresh()->getRawOriginal())->toBe($before);
    $this->assertDatabaseMissing('operator_activities', ['type' => OperatorActivityType::StageOverdue->value]);
});

it('exposes real submission deadline and overdue events without result links or markup', function () {
    Sanctum::actingAs(User::factory()->manager()->create());
    $operator = User::factory()->operator()->create(['name' => '<b>Operator</b>']);
    $project = Project::factory()->create(['name' => '<b>Album</b>', 'status' => ProjectStatus::InProgress]);
    $stage = $project->stages()->create(['stage' => StageType::Editing, 'sequence' => 1,
        'status' => StageStatus::Ready, 'operator_id' => $operator->id]);
    $track = $project->tracks()->create(['title' => '<i>Intro</i>', 'position' => 1]);
    $progress = $stage->trackProgress()->create(['project_track_id' => $track->id, 'status' => TrackProgressStatus::Pending]);
    app(ConfirmStageDeadline::class)->handle($stage, $operator, CarbonImmutable::now()->addHour());
    app(SubmitTrackResult::class)->handle($progress, $track, $operator, 'https://private.example/result?token=secret');
    $this->travel(2)->hours();
    app(RecordOverdueStages::class)->handle();

    $response = $this->getJson('/api/v1/manager/dashboard/operator-activities')->assertOk()->assertJsonCount(3, 'data.items')
        ->assertJsonPath('data.items.0.type', 'stage_overdue')
        ->assertJsonPath('data.items.0.actor.is_system', true)
        ->assertJsonPath('data.items.1.type', 'result_submitted')
        ->assertJsonPath('data.items.1.track.title', 'Intro')
        ->assertJsonPath('data.items.2.type', 'deadline_confirmed')
        ->assertJsonPath('data.items.2.actor.name', 'Operator');
    expect($response->getContent())->not->toContain('private.example', 'token=', 'result_url', '<b>', '<i>');
});
