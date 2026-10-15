<?php

use App\Enum\ProjectStatus;
use App\Enum\StageStatus;
use App\Enum\TrackProgressStatus;
use App\Models\Package;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\TrackStageProgress;
use App\Models\User;

function createProgressProject(array $includedStages, int $trackCount = 2): Project
{
    $package = Package::factory()->create();
    $project = Project::factory()->create([
        'package_id' => $package->id,
        'status' => ProjectStatus::InProgress,
        'package_snapshot' => ['stages' => $includedStages],
    ]);

    foreach ($includedStages as $index => $stageType) {
        $stage = ProjectStage::query()->create([
            'project_id' => $project->id,
            'stage' => $stageType,
            'sequence' => ($index + 1) * 2,
            'operator_id' => User::factory()->operator()->create()->id,
            'status' => $index === 0 ? StageStatus::Ready : StageStatus::Locked,
            'assigned_at' => now(),
        ]);

        if ($index === 0) {
            $project->tracks()->createMany(collect(range(1, $trackCount))->map(fn (int $trackIndex): array => [
                'title' => "Track {$trackIndex}",
                'position' => $trackIndex,
            ])->all());
        }

        for ($trackIndex = 1; $trackIndex <= $trackCount; $trackIndex++) {
            $track = $project->tracks()->where('position', $trackIndex)->firstOrFail();

            TrackStageProgress::query()->create([
                'project_stage_id' => $stage->id,
                'project_track_id' => $track->id,
            ]);
        }
    }

    return $project;
}

it('calculates project progress from approved active package cells only', function () {
    $project = createProgressProject(['recording', 'mastering']);
    $progress = TrackStageProgress::query()->orderBy('id')->get();

    expect($project->progressPercent())->toBe(0);

    $progress[0]->update(['status' => TrackProgressStatus::Approved]);
    expect($project->progressPercent())->toBe(25);

    $progress->each(fn (TrackStageProgress $cell) => $cell->update(['status' => TrackProgressStatus::Approved]));
    expect($project->progressPercent())->toBe(100);

    ProjectStage::query()->create([
        'project_id' => $project->id,
        'stage' => 'editing',
        'sequence' => 1,
        'status' => StageStatus::Completed,
    ]);
    expect($project->progressPercent())->toBe(100);
});

it('returns zero progress when package has no active stages or cells', function () {
    expect(createProgressProject([])->progressPercent())->toBe(0)
        ->and(createProgressProject(['editing'], 0)->progressPercent())->toBe(0);
});
