<?php

namespace App\Actions\Project;

use App\Enum\ProjectStatus;
use App\Enum\QuotationStatus;
use App\Enum\SessionStatus;
use App\Enum\StageStatus;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\RecordingSession;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;

class GetManagerDashboardProgress
{
    /**
     * @return array{
     * recording_schedule: list<array{id: int, project_id: ?int, project_name: ?string, client_id: ?int, client_name: ?string, start_at: string, end_at: string, status: string, status_label: string}>,
     * pending_project_assignments: int,
     * project_progress: list<array{id: int, name: string, client_id: ?int, client_name: ?string, status: string, status_label: string, current_stage: ?string, current_stage_label: ?string, stage_status: ?string, stage_status_label: ?string}>,
     * quotation_progress: list<array{id: int, name: ?string, package_display_name: string, client_id: ?int, client_name: ?string, status: string, status_label: string}>
     * }
     */
    public function handle(?CarbonImmutable $asOf = null): array
    {
        $timezone = config('app.timezone');
        $today = ($asOf ?? CarbonImmutable::now($timezone))->setTimezone($timezone)->startOfDay();
        $sessions = RecordingSession::query()->select('id', 'project_id', 'client_id', 'start_at', 'end_at', 'status')
            ->where('status', SessionStatus::Confirmed->value)->overlapping($today, $today->addDay())
            ->with(['project:id,name', 'client:id,name'])->orderBy('start_at')->orderBy('id')->get();
        $projects = Project::query()->select('id', 'name', 'client_id', 'status')
            ->where('status', ProjectStatus::InProgress->value)
            ->with(['client:id,name', 'stages' => function (Relation $query): void {
                $query->select('id', 'project_id', 'stage', 'status', 'sequence')
                    ->whereIn('status', [StageStatus::Ready->value, StageStatus::InProgress->value])
                    ->orderBy('sequence')->orderBy('id');
            }])->orderByDesc('updated_at')->orderByDesc('id')->get();
        $pendingProjectAssignments = Project::query()
            ->where('status', ProjectStatus::AwaitingAssignment->value)
            ->count();
        $quotations = Quotation::query()->select('id', 'name', 'client_id', 'status')
            ->whereIn('status', [QuotationStatus::AwaitingStudio->value, QuotationStatus::AwaitingClient->value])
            ->with('client:id,name')->orderByDesc('updated_at')->orderByDesc('id')->get();

        return [
            'pending_project_assignments' => $pendingProjectAssignments,
            'recording_schedule' => $sessions->map(fn (RecordingSession $session): array => [
                'id' => $session->id, 'project_id' => $session->project_id, 'project_name' => $session->project?->name,
                'client_id' => $session->client_id, 'client_name' => $session->client?->name,
                'start_at' => $session->start_at->copy()->setTimezone($timezone)->toIso8601String(),
                'end_at' => $session->end_at->copy()->setTimezone($timezone)->toIso8601String(),
                'status' => $session->status->value, 'status_label' => $session->status->label(),
            ])->all(),
            'project_progress' => $projects->map(function (Project $project): array {
                $stage = $project->stages->first();

                return [
                    'id' => $project->id, 'name' => $project->name,
                    'client_id' => $project->client_id, 'client_name' => $project->client?->name,
                    'status' => $project->status->value, 'status_label' => $project->status->label(),
                    'current_stage' => $stage?->stage->value, 'current_stage_label' => $stage?->stage->label(),
                    'stage_status' => $stage?->status->value, 'stage_status_label' => $stage?->status->label(),
                ];
            })->all(),
            'quotation_progress' => $quotations->map(fn (Quotation $quotation): array => [
                'id' => $quotation->id, 'name' => $quotation->name, 'package_display_name' => $quotation->packageDisplayName(),
                'client_id' => $quotation->client_id, 'client_name' => $quotation->client?->name,
                'status' => $quotation->status->value, 'status_label' => $quotation->status->label(),
            ])->all(),
        ];
    }
}
