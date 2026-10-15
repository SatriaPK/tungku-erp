<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Actions\Project\AssignOperators;
use App\Http\Controllers\Controller;
use App\Http\Requests\Manager\AssignOperatorsRequest;
use App\Http\Resources\ProjectStageAssignmentResource;
use App\Models\Project;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProjectAssignmentController extends Controller
{
    public function store(AssignOperatorsRequest $request, Project $project, AssignOperators $action): AnonymousResourceCollection
    {
        return ProjectStageAssignmentResource::collection(
            $action->handle($project, $request->assignments()),
        )->additional([
            'meta' => [
                'project_id' => $project->id,
                'project_status' => 'in_progress',
            ],
        ]);
    }
}
