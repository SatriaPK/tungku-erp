<?php

use App\Actions\Monitoring\SanitizeErrorEvent;

return [
    'dsn' => in_array(env('APP_ENV', 'production'), ['local', 'testing'], true)
        ? null : env('SENTRY_LARAVEL_DSN'),
    'environment' => env('SENTRY_ENVIRONMENT', env('APP_ENV', 'production')),
    'release' => env('SENTRY_RELEASE'),
    'sample_rate' => (float) env('SENTRY_SAMPLE_RATE', 1.0),
    'traces_sample_rate' => null,
    'profiles_sample_rate' => null,
    'send_default_pii' => false,
    'send_client_reports' => false,
    'enable_logs' => false,
    'max_request_body_size' => 'never',
    'attach_stacktrace' => false,
    'context_lines' => 0,
    'before_send' => [SanitizeErrorEvent::class, 'handle'],
    'before_send_transaction' => [SanitizeErrorEvent::class, 'handle'],
    'breadcrumbs' => [
        'logs' => false, 'cache' => false, 'livewire' => false,
        'sql_queries' => false, 'sql_bindings' => false, 'queue_info' => false,
        'command_info' => false, 'http_client_requests' => false, 'notifications' => false,
    ],
    'tracing' => ['default_integrations' => false],
];
