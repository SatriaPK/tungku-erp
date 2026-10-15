<div>
    <h1>Payment Failed or Expired</h1>
    <p>We are sorry, but your payment for Order: <strong>{{ $payment->order_id }}</strong> has failed or expired.</p>
    <p>Amount: <strong>{{ number_format($payment->amount, 0, ',', '.') }}</strong></p>
    <br>
    <p>Please contact support or create a new booking.</p>
</div>

