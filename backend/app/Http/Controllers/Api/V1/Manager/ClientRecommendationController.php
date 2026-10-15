<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Actions\CRM\GetClientRecommendations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Manager\ClientRecommendationRequest;
use App\Http\Resources\Manager\ClientRecommendationResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClientRecommendationController extends Controller
{
    public function index(ClientRecommendationRequest $request, GetClientRecommendations $action): AnonymousResourceCollection
    {
        $validated = $request->validated();

        return ClientRecommendationResource::collection($action->handle(
            (int) ($validated['per_page'] ?? 15),
        ));
    }
}
