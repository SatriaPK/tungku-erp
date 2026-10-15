<div>
    <h1>New Invoice Issued</h1>
    <p>A new invoice has been issued: <strong>{{ $invoice->invoice_number }}</strong></p>
    <p>Total Amount: <strong>{{ number_format($invoice->total ?? 0, 0, ',', '.') }}</strong></p>
    <br>
    <p>Please keep this for your records.</p>
</div>

