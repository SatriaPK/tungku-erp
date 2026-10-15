<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Actions\CRM\CreateCampaign;
use App\Actions\CRM\ListCampaigns;
use App\Actions\CRM\SendCampaign;
use App\Http\Controllers\Controller;
use App\Http\Requests\Manager\CreateCRMCampaignRequest;
use App\Http\Requests\Manager\ListCRMCampaignsRequest;
use App\Http\Resources\Manager\CRMCampaignResource;
use App\Models\CRMCampaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CRMCampaignController extends Controller
{
    public function index(ListCRMCampaignsRequest $request, ListCampaigns $action): AnonymousResourceCollection
    {
        $perPage = (int) ($request->validated()['per_page'] ?? 15);

        return CRMCampaignResource::collection($action->handle($perPage));
    }

    public function store(CreateCRMCampaignRequest $request, CreateCampaign $action): JsonResponse
    {
        $campaign = $action->handle($request->user(), $request->validated());

        return (new CRMCampaignResource($campaign->loadCount('recipients')->loadCount([
            'recipients as pending_recipients_count' => fn ($query) => $query->where('status', 'pending'),
            'recipients as sent_recipients_count' => fn ($query) => $query->where('status', 'sent'),
            'recipients as failed_recipients_count' => fn ($query) => $query->where('status', 'failed'),
        ])))->response()->setStatusCode(201);
    }

    public function show(CRMCampaign $campaign): CRMCampaignResource
    {
        return new CRMCampaignResource($campaign->load([
            'recipients' => fn ($query) => $query->with('client:id,name')->orderBy('id'),
        ])->loadCount([
            'recipients',
            'recipients as pending_recipients_count' => fn ($query) => $query->where('status', 'pending'),
            'recipients as sent_recipients_count' => fn ($query) => $query->where('status', 'sent'),
            'recipients as failed_recipients_count' => fn ($query) => $query->where('status', 'failed'),
        ]));
    }

    public function dispatch(CRMCampaign $campaign, SendCampaign $action): JsonResponse
    {
        $action->handle($campaign);

        return response()->json(['data' => ['id' => $campaign->id, 'status' => 'sending']], 202);
    }
}
