<?php

namespace App\Http\Controllers\Api\V1\Operator;

use App\Http\Controllers\Controller;
use App\Http\Resources\Operator\OperatorStageResource;
use App\Http\Resources\RoomResource;
use App\Models\ProjectStage;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OperatorWorkspaceController extends Controller
{
    public function rooms(): AnonymousResourceCollection
    {
        return RoomResource::collection(Room::query()->active()->orderBy('name')->get());
    }

    public function stages(Request $request): AnonymousResourceCollection
    {
        $stages = ProjectStage::query()
            ->where('operator_id', $request->user()->id)
            ->with(['project.client:id,name', 'trackProgress.track:id,title'])
            ->latest('assigned_at')
            ->paginate(15);

        return OperatorStageResource::collection($stages);
    }
}
