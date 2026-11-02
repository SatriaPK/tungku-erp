<?php

namespace App\Http\Resources\Manager;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class OperatorResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->copy()->setTimezone(config('app.timezone'))->toIso8601String(),
            'assignments_count' => (int) ($this->active_assignments_count ?? 0),
            'assigned_projects' => $this->whenLoaded('stageAssignments', function () use ($request) {
                return $this->stageAssignments->groupBy('project_id')->map(function ($stages) use ($request) {
                    $project = $stages->first()->project;

                    $data = Arr::only((new ProjectIndexResource($project))->resolve($request), [
                            'id', 'name', 'status', 'status_label', 'client', 'current_stage',
                            'progress_percent', 'started_at', 'created_at',
                        ]);

                    return [
                        'id' => $data['id'],
                        'name' => $data['name'],
                        'status' => $data['status'],
                        'status_label' => $data['status_label'],
                        'client' => $data['client'],
                        'current_stage' => $data['current_stage'],
                        'progress_percent' => $data['progress_percent'],
                        'started_at' => $data['started_at'],
                        'created_at' => $data['created_at'],
                        'stages' => $stages->map(fn ($stage) => $stage->stage->label())->values()->all(),
                    ];
                })->values()->all();
            }),
        ];
    }
}
