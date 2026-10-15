<div>
    <h1>Payment Confirmed</h1>
    <p>Thank you! We have received your payment for Order: <strong>{{ $payment->order_id }}</strong></p>
    <p>Amount Paid: <strong>{{ number_format($payment->amount, 0, ',', '.') }}</strong></p>
    <p>Payment Method: {{ $payment->payment_method ?? 'Not specified' }}</p>
    <br>
    <p>Your payment has been successfully verified.</p>
</div>

