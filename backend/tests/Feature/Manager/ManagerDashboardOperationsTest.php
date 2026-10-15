<?php

use App\Actions\Finance\GetManagerDashboardMetrics;
use App\Enum\PaymentStatus;
use App\Enum\PaymentType;
use App\Enum\ProjectStatus;
use App\Enum\QuotationStatus;
use App\Enum\SessionStatus;
use App\Enum\StageStatus;
use App\Enum\StageType;
use App\Enum\TrackProgressStatus;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\RecordingSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

it('returns stable empty operational counts', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15', 'Asia/Jakarta'));
    $metrics = app(GetManagerDashboardMetrics::class)->handle();

    expect($metrics['awaiting_final_payment'])->toBe(['amount' => 0, 'project_count' => 0, 'positive_balance_project_count' => 0])
        ->and($metrics['in_progress_projects'])->toBe(['count' => 0, 'track_count' => 0])
        ->and($metrics['unclassified_track_count'])->toBe(0)
        ->and($metrics['quotations'])->toBe(['open_count' => 0, 'awaiting_studio_count' => 0])
        ->and($metrics['tracks_by_stage'])->toBe([
            ['stage' => 'recording', 'stage_label' => 'Recording', 'track_count' => 0],
            ['stage' => 'editing', 'stage_label' => 'Editing', 'track_count' => 0],
            ['stage' => 'mixing', 'stage_label' => 'Mixing', 'track_count' => 0],
            ['stage' => 'mastering', 'stage_label' => 'Mastering', 'track_count' => 0],
        ]);
});

it('selects exact project statuses and clamps each lifetime balance before summing', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15', 'Asia/Jakarta'));
    foreach (ProjectStatus::cases() as $status) {
        $project = Project::factory()->create(['status' => $status, 'price' => 1000, 'po_amount' => 900, 'final_payment_amount' => 9999]);
        $project->tracks()->createMany([['title' => 'A', 'position' => 1], ['title' => 'B', 'position' => 2]]);
        Payment::factory()->paid()->create(['project_id' => $project->id, 'type' => PaymentType::Po, 'amount' => 200, 'paid_at' => '2025-01-01']);
        Payment::factory()->paid()->create(['project_id' => $project->id, 'type' => PaymentType::Final, 'amount' => 100, 'paid_at' => '2025-02-01']);
        foreach (SessionStatus::cases() as $sessionStatus) {
            RecordingSession::factory()->create(['project_id' => $project->id, 'is_extension' => true, 'status' => $sessionStatus, 'extra_fee' => 50]);
        }
        RecordingSession::factory()->confirmed()->create(['project_id' => $project->id, 'is_extension' => false, 'extra_fee' => 9000]);
    }
    foreach ([1000, 2000] as $paid) {
        $project = Project::factory()->create(['status' => ProjectStatus::AwaitingFinalPayment, 'price' => 1000]);
        Payment::factory()->paid()->create(['project_id' => $project->id, 'amount' => $paid]);
    }
    $unpaid = Project::factory()->create(['status' => ProjectStatus::AwaitingFinalPayment, 'price' => 500]);
    Payment::factory()->create(['project_id' => $unpaid->id, 'status' => PaymentStatus::Pending, 'amount' => 500]);

    $metrics = app(GetManagerDashboardMetrics::class)->handle();

    expect($metrics['awaiting_final_payment'])->toBe(['amount' => 1300, 'project_count' => 4, 'positive_balance_project_count' => 2])
        ->and($metrics['in_progress_projects'])->toBe(['count' => 1, 'track_count' => 2])
        ->and($metrics['unclassified_track_count'])->toBe(2)
        ->and(Project::where('status', ProjectStatus::AwaitingFinalPayment)->count())->toBe(4);
});

it('ignores every unpaid payment status when computing outstanding balances', function (PaymentStatus $status) {
    $this->travelTo(CarbonImmutable::parse('2026-10-15', 'Asia/Jakarta'));
    $project = Project::factory()->create(['status' => ProjectStatus::AwaitingFinalPayment, 'price' => 1000]);
    Payment::factory()->create(['project_id' => $project->id, 'status' => $status, 'amount' => 1000, 'paid_at' => '2026-10-01']);
    expect(app(GetManagerDashboardMetrics::class)->handle()['awaiting_final_payment']['amount'])->toBe(1000);
})->with(array_filter(PaymentStatus::cases(), fn (PaymentStatus $status): bool => $status !== PaymentStatus::Paid));

it('counts tracks once across stage cells and assigns only the first eligible stage', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15', 'Asia/Jakarta'));
    foreach (StageType::cases() as $currentStage) {
        $project = Project::factory()->create(['status' => ProjectStatus::InProgress]);
        $tracks = $project->tracks()->createMany([['title' => 'A', 'position' => 1], ['title' => 'B', 'position' => 2]]);
        foreach (StageType::cases() as $stageType) {
            $stage = $project->stages()->create([
                'stage' => $stageType, 'sequence' => $stageType->sequence(),
                'status' => $stageType === $currentStage ? StageStatus::Ready : StageStatus::Locked,
            ]);
            foreach ($tracks as $track) {
                $stage->trackProgress()->create([
                    'project_track_id' => $track->id, 'status' => TrackProgressStatus::cases()[$stageType->sequence() - 1],
                    'result_url' => 'https://private.example/locked-result',
                ]);
            }
        }
    }
    $gap = Project::factory()->create(['status' => ProjectStatus::InProgress]);
    $gap->tracks()->create(['title' => 'Gap', 'position' => 1]);
    $gap->stages()->create(['stage' => StageType::Recording, 'sequence' => 1, 'status' => StageStatus::Completed]);
    $skipped = Project::factory()->create(['status' => ProjectStatus::InProgress]);
    $skipped->tracks()->create(['title' => 'Skipped recording', 'position' => 1]);
    $skipped->stages()->create(['stage' => StageType::Mixing, 'sequence' => 2, 'status' => StageStatus::InProgress]);
    $skipped->stages()->create(['stage' => StageType::Mastering, 'sequence' => 2, 'status' => StageStatus::Ready]);

    DB::enableQueryLog();
    try {
        $metrics = app(GetManagerDashboardMetrics::class)->handle();
        $queries = DB::getQueryLog();
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }

    expect($metrics['in_progress_projects'])->toBe(['count' => 6, 'track_count' => 10])
        ->and(array_column($metrics['tracks_by_stage'], 'track_count', 'stage'))->toBe(['recording' => 2, 'editing' => 2, 'mixing' => 3, 'mastering' => 2])
        ->and($metrics['unclassified_track_count'])->toBe(1)
        ->and(json_encode($metrics))->not->toContain('private.example', 'result_url');
    foreach ($queries as $query) {
        expect(strtolower(ltrim($query['query'])))->toStartWith('select');
    }
});

it('counts only open quotations and the studio subset', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15', 'Asia/Jakarta'));
    $client = User::factory()->client()->create();
    foreach ([QuotationStatus::AwaitingStudio, ...QuotationStatus::cases()] as $status) {
        Quotation::create(['client_id' => $client->id, 'track_count' => 1, 'recording_hours' => 1, 'auto_price' => 1000, 'status' => $status]);
    }
    expect(app(GetManagerDashboardMetrics::class)->handle()['quotations'])->toBe(['open_count' => 3, 'awaiting_studio_count' => 2]);
});
