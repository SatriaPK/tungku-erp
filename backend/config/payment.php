<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Payment Gateway
    |--------------------------------------------------------------------------
    |
    | This option controls the default payment gateway used by the application.
    | Online payments use Xendit. Supported: "xendit", "manual"
    |
    */

    'default_gateway' => env('PAYMENT_DEFAULT_GATEWAY', 'xendit'),

    /*
    |--------------------------------------------------------------------------
    | Payment Expiration
    |--------------------------------------------------------------------------
    |
    | How long a payment link/charge remains valid before expiring (in hours).
    |
    */

    'expiration_hours' => (int) env('PAYMENT_EXPIRATION_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Xendit Configuration
    |--------------------------------------------------------------------------
    |
    | Test vs live mode is decided by the secret key itself: keys starting with
    | "xnd_development_" create simulated payments, "xnd_production_" real ones.
    | After payment Xendit redirects the client back to the client portal.
    |
    */

    'xendit' => [
        'secret_key' => env('XENDIT_SECRET_KEY'),
        'webhook_token' => env('XENDIT_WEBHOOK_TOKEN'),
        'base_url' => env('XENDIT_BASE_URL', 'https://api.xendit.co'),
        'success_redirect_url' => env('XENDIT_SUCCESS_REDIRECT_URL', rtrim((string) env('CLIENT_PORTAL_URL', env('FRONTEND_URL', 'http://localhost:5173')), '/').'/client?payment=success'),
        'failure_redirect_url' => env('XENDIT_FAILURE_REDIRECT_URL', rtrim((string) env('CLIENT_PORTAL_URL', env('FRONTEND_URL', 'http://localhost:5173')), '/').'/client?payment=failed'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Manual Payment Configuration
    |--------------------------------------------------------------------------
    |
    | Manual payments (cash, bank transfer) require manager verification.
    |
    */

    'manual' => [
        'requires_approval' => true,
        'default_expiration_days' => 7,
    ],

    'refund_window_days' => (int) env('PAYMENT_REFUND_WINDOW_DAYS', 90),
];
