<div>
    <h1>Refund Processed</h1>
    <p>We have successfully processed a refund for your payment Order: <strong>{{ $payment->order_id }}</strong></p>
    <p>Refund Amount: <strong>{{ number_format($payment->amount, 0, ',', '.') }}</strong></p>
    <br>
    <p>The funds should reflect in your account depending on your payment provider.</p>
</div>

