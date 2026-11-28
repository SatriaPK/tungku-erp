<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceListResource;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

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

    /**
     * Download the invoice as a PDF.
     */
    public function pdf(Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);

        $invoice->load(['client', 'project', 'items']);

        return Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'studioName' => config('app.name') === 'Laravel' ? 'Tungku Studio' : config('app.name'),
        ])->setPaper('a4')->download($invoice->invoice_number.'.pdf');
    }
}
