<?php

use App\Actions\Project\GetManagerDashboardProgress;
use App\Enum\ProjectStatus;
use App\Enum\QuotationStatus;
use App\Enum\SessionStatus;
use App\Enum\StageStatus;
use App\Enum\StageType;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\RecordingSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

it('returns empty lists', function () {
    expect(app(GetManagerDashboardProgress::class)->handle())->toBe([
        'recording_schedule' => [], 'project_progress' => [], 'quotation_progress' => [],
    ]);
});

it('uses the local day overlap and confirmed status without repairing elapsed sessions', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-01 12:00:00', 'Asia/Jakarta'));
    $asOf = CarbonImmutable::parse('2026-10-14 17:01:00', 'UTC');
    $client = User::factory()->client()->create(['name' => 'Pemesan']);
    $project = Project::factory()->create(['status' => ProjectStatus::Cancelled, 'name' => 'Project dibatalkan']);
    $expectedIds = [];
    foreach ([['2026-10-14 23:00:00', '2026-10-15 01:00:00'], ['2026-10-15 00:00:00', '2026-10-15 01:00:00'],
        ['2026-10-15 10:00:00', '2026-10-15 11:00:00'], ['2026-10-15 23:00:00', '2026-10-16 01:00:00']] as [$start, $end]) {
        $expectedIds[] = RecordingSession::factory()->confirmed()->create([
            'project_id' => $project->id, 'client_id' => $client->id, 'start_at' => $start, 'end_at' => $end, 'is_extension' => true,
        ])->id;
    }
    foreach (SessionStatus::cases() as $status) {
        if ($status !== SessionStatus::Confirmed) {
            RecordingSession::factory()->create(['status' => $status, 'start_at' => '2026-10-15 10:00:00', 'end_at' => '2026-10-15 11:00:00']);
        }
    }
    RecordingSession::factory()->confirmed()->create(['start_at' => '2026-10-14 23:00:00', 'end_at' => '2026-10-15 00:00:00']);
    RecordingSession::factory()->confirmed()->create(['start_at' => '2026-10-16 00:00:00', 'end_at' => '2026-10-16 01:00:00']);
    $orphan = RecordingSession::factory()->confirmed()->create(['project_id' => null, 'start_at' => '2026-10-15 23:00:00', 'end_at' => '2026-10-16 01:00:00']);
    $expectedIds[] = $orphan->id;
    $result = app(GetManagerDashboardProgress::class)->handle($asOf);
    expect(array_column($result['recording_schedule'], 'id'))->toBe($expectedIds)
        ->and($result['recording_schedule'][0])->toBe([
            'id' => $expectedIds[0], 'project_id' => $project->id, 'project_name' => 'Project dibatalkan',
            'client_id' => $client->id, 'client_name' => 'Pemesan', 'start_at' => '2026-10-14T23:00:00+07:00',
            'end_at' => '2026-10-15T01:00:00+07:00', 'status' => 'confirmed', 'status_label' => SessionStatus::Confirmed->label(),
        ])
        ->and($result['recording_schedule'][4]['project_name'])->toBeNull()
        ->and(app(GetManagerDashboardProgress::class)->handle($asOf->setTimezone('Asia/Jakarta')))->toBe($result)
        ->and(app(GetManagerDashboardProgress::class)->handle($asOf->setTimezone('Asia/Jakarta')->setTime(23, 59)))->toBe($result);
});

it('lists all running projects with the first eligible stage and honest gaps', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15', 'Asia/Jakarta'));
    $client = User::factory()->client()->create(['name' => 'Client', 'email' => 'private@example.com']);
    foreach (ProjectStatus::cases() as $status) {
        if ($status !== ProjectStatus::InProgress) {
            Project::factory()->create(['status' => $status]);
        }
    }
    $ids = [];
    foreach (StageType::cases() as $type) {
        $project = Project::factory()->create(['status' => ProjectStatus::InProgress, 'client_id' => $client->id, 'name' => $type->label()]);
        $ids[] = $project->id;
        foreach (StageType::cases() as $stage) {
            $project->stages()->create(['stage' => $stage, 'sequence' => $stage->sequence(),
                'status' => $stage === $type ? StageStatus::Ready : StageStatus::Locked]);
        }
    }
    $gap = Project::factory()->create(['status' => ProjectStatus::InProgress]);
    $gap->stages()->create(['stage' => StageType::Recording, 'sequence' => 1, 'status' => StageStatus::Completed]);
    $skipped = Project::factory()->create(['status' => ProjectStatus::InProgress]);
    $skipped->stages()->create(['stage' => StageType::Mixing, 'sequence' => 2, 'status' => StageStatus::InProgress]);
    $skipped->stages()->create(['stage' => StageType::Mastering, 'sequence' => 2, 'status' => StageStatus::Ready]);
    $projects = app(GetManagerDashboardProgress::class)->handle()['project_progress'];
    expect(array_column($projects, 'id'))->toBe([$skipped->id, $gap->id, ...array_reverse($ids)])
        ->and($projects[0]['current_stage'])->toBe('mixing')->and($projects[0]['stage_status'])->toBe('in_progress')
        ->and($projects[1]['current_stage'])->toBeNull()->and($projects[1]['stage_status'])->toBeNull()
        ->and(array_column(array_slice($projects, 2), 'current_stage'))->toBe(['mastering', 'mixing', 'editing', 'recording'])
        ->and($projects[5]['client_name'])->toBe('Client')
        ->and($projects[5]['stage_status'])->toBe('ready')
        ->and(json_encode($projects))->not->toContain('private@example.com', 'result_url', 'review_notes');
    $gap->forceFill(['updated_at' => now()->addSecond()])->save();
    expect(app(GetManagerDashboardProgress::class)->handle()['project_progress'][0]['id'])->toBe($gap->id);
});

it('returns every open quotation with names and legacy fallbacks in stable order', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15', 'Asia/Jakarta'));
    $client = User::factory()->client()->create(['name' => 'Pemesan']);
    $ids = [];
    foreach ([QuotationStatus::Accepted, QuotationStatus::Declined, QuotationStatus::AwaitingStudio, QuotationStatus::AwaitingClient, QuotationStatus::AwaitingClient, QuotationStatus::AwaitingStudio] as $status) {
        $quotation = Quotation::create(['client_id' => $client->id, 'name' => 'Judul dari klien', 'track_count' => 1, 'recording_hours' => 0, 'auto_price' => 10, 'status' => $status]);
        if ($status->isOpen()) {
            $ids[] = $quotation->id;
        }
    }
    $quotation->update(['name' => null]);
    DB::enableQueryLog();
    try {
        $result = app(GetManagerDashboardProgress::class)->handle();
        $queries = DB::getQueryLog();
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
    expect(array_column($result['quotation_progress'], 'id'))->toBe(array_reverse($ids))
        ->and($result['quotation_progress'][0])->toBe([
            'id' => $quotation->id, 'name' => null, 'package_display_name' => 'Penawaran Kustom #'.$quotation->id,
            'client_id' => $client->id, 'client_name' => 'Pemesan', 'status' => 'awaiting_studio', 'status_label' => QuotationStatus::AwaitingStudio->label(),
        ])
        ->and($result['quotation_progress'][1]['package_display_name'])->toBe('Judul dari klien')
        ->and(count($queries))->toBeLessThanOrEqual(8);
    foreach ($queries as $query) {
        expect(strtolower(ltrim($query['query'])))->toStartWith('select');
    }
});
