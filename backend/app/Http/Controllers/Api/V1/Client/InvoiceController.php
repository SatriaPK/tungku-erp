<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceListResource;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;

class InvoiceController extends Controller
{
    /**
     * List client's own invoices.
     */
    public function index()
    {
        $invoices = Invoice::where('client_id', request()->user()->id)
            ->with(['project'])
            ->latest('created_at')
            ->paginate(15);

        return InvoiceListResource::collection($invoices);
    }

    /**
     * Show a single invoice detail.
     */
    public function show(Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);

        $invoice->load(['client', 'project', 'recordingSession', 'items', 'latestPayment']);

        return (new InvoiceResource($invoice))->response();
    }
}
