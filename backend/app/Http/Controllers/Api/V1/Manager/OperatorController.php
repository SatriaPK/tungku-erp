<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Actions\Project\LoadProjectCardProgress;
use App\Enum\ProjectStatus;
use App\Enum\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Manager\StoreOperatorRequest;
use App\Http\Requests\Manager\UpdateOperatorStatusRequest;
use App\Http\Resources\Manager\OperatorResource;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OperatorController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $activeCount = User::query()->operators()->where('is_active', true)->count();
        $activeProjectStatuses = array_map(
            static fn ($status): string => $status->value,
            array_filter(ProjectStatus::cases(), static fn ($status): bool => $status->isActive()),
        );
        $operators = User::query()
            ->operators()
            ->with([
                'stageAssignments' => static function ($query) use ($activeProjectStatuses): void {
                    $query->whereIn(
                        'project_id',
                        Project::query()->select('id')->whereIn('status', $activeProjectStatuses),
                    )->with('project.client:id,name', 'project.currentStage.operator:id,name')->orderBy('project_id')->orderBy('sequence');
                },
            ])
            ->withCount([
                'stageAssignments as active_assignments_count' => static function ($query) use ($activeProjectStatuses): void {
                    $query->whereIn(
                        'project_id',
                        Project::query()->select('id')->whereIn('status', $activeProjectStatuses),
                    );
                },
            ])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->query('search'));
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(min(max((int) $request->query('per_page', 10), 1), 100));

        $projects = $operators->getCollection()
            ->flatMap(fn (User $operator) => $operator->stageAssignments->pluck('project'));
        app(LoadProjectCardProgress::class)->execute($projects);

        return OperatorResource::collection($operators)->additional([
            'meta' => ['active_count' => $activeCount],
        ]);
    }

    public function store(StoreOperatorRequest $request): JsonResponse
    {
        $operator = new User([
            ...$request->safe()->only(['name', 'email', 'phone', 'password']),
            'crm_consent_at' => null,
        ]);
        $operator->role = Role::Operator;
        $operator->is_active = true;
        $operator->save();

        return (new OperatorResource($operator))->response()->setStatusCode(201);
    }

    public function updateStatus(UpdateOperatorStatusRequest $request, User $operator): OperatorResource
    {
        abort_unless($operator->isOperator(), 404);

        $operator->forceFill(['is_active' => $request->validated('is_active')])->save();

        return new OperatorResource($operator);
    }
}
