<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Actions\Quotation\RespondToQuotation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Manager\RespondToQuotationRequest;
use App\Http\Resources\QuotationResource;
use App\Models\Quotation;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class QuotationController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Quotation::class);

        $quotations = Quotation::with('client')
            ->with('latestOffer')
            ->latest()
            ->paginate(15);

        return QuotationResource::collection($quotations);
    }

    public function show(Quotation $quotation): QuotationResource
    {
        $this->authorize('view', $quotation);

        $quotation->load(['offers', 'client']);

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
