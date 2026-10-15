<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Actions\Quotation\RespondToQuotation;
use App\Actions\Quotation\SubmitQuotation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\RespondToQuotationRequest;
use App\Http\Requests\Client\SubmitQuotationRequest;
use App\Http\Resources\QuotationResource;
use App\Models\Quotation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class QuotationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Quotation::class);

        $quotations = Quotation::where('client_id', $request->user()->id)
            ->with('latestOffer')
            ->latest()
            ->paginate(15);

        return QuotationResource::collection($quotations);
    }

    public function show(Quotation $quotation, Request $request): QuotationResource
    {
        $this->authorize('view', $quotation);

        $quotation->load(['offers']);

        return new QuotationResource($quotation);
    }

    public function store(SubmitQuotationRequest $request, SubmitQuotation $action): QuotationResource
    {
        $quotation = $action->handle($request->user(), $request->validated());

        return new QuotationResource($quotation);
    }

    public function respond(Quotation $quotation, RespondToQuotationRequest $request, RespondToQuotation $action): QuotationResource
    {
        $this->authorize('respond', $quotation);

        try {
            $updated = $action->handle($quotation, $request->user(), $request->validated());

            return new QuotationResource($updated);
        } catch (\RuntimeException $e) {
            abort(400, $e->getMessage());
        } catch (\InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }
    }
}
