<div>
    <h1>Payment Request for your booking</h1>
    <p>We have created a payment request for Order: <strong>{{ $payment->order_id }}</strong></p>
    <p>Amount: <strong>{{ number_format($payment->amount, 0, ',', '.') }}</strong></p>
    <p>Please complete your payment before {{ $payment->expires_at?->format('d M Y H:i') ?? 'the deadline' }}</p>
    <br>
    <a href="{{ $url ?? url('/') }}">Pay Now</a>
</div>

