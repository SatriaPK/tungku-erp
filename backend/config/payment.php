<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Payment Gateway
    |--------------------------------------------------------------------------
    |
    | This option controls the default payment gateway used by the application.
    | Supported: "midtrans", "xendit", "manual"
    |
    */

    'default_gateway' => env('PAYMENT_DEFAULT_GATEWAY', 'midtrans'),

    /*
    |--------------------------------------------------------------------------
    | Payment Expiration
    |--------------------------------------------------------------------------
    |
    | How long a payment link/charge remains valid before expiring (in hours).
    |
    */

    'expiration_hours' => env('PAYMENT_EXPIRATION_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Midtrans Configuration
    |--------------------------------------------------------------------------
    */

    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
        'is_sanitized' => env('MIDTRANS_SANITIZED', true),
        'is_3ds' => env('MIDTRANS_3DS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Xendit Configuration
    |--------------------------------------------------------------------------
    */

    'xendit' => [
        'secret_key' => env('XENDIT_SECRET_KEY'),
        'webhook_token' => env('XENDIT_WEBHOOK_TOKEN'),
        'is_production' => env('XENDIT_IS_PRODUCTION', false),
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

    'refund_window_days' => env('PAYMENT_REFUND_WINDOW_DAYS', 90),
];
