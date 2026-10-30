<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

test('OpenAPI exports all API routes with safe authentication and response contracts', function () {
    expect(Artisan::call('api:docs', ['--output' => storage_path('app/private')]))->toBe(0);
    $spec = json_decode(file_get_contents(storage_path('app/private/openapi.json')), true, flags: JSON_THROW_ON_ERROR);
    expect($spec['openapi'])->toBe('3.1.2');
    expect(file_get_contents(storage_path('app/private/API_REFERENCE.md')))->toContain('POST /api/v1/auth/login', 'NotificationResource', 'Retry-After');
    foreach (Route::getRoutes() as $route) {
        if (! str_starts_with($route->uri(), 'api/')) {
            continue;
        }
        foreach (array_diff($route->methods(), ['HEAD']) as $method) {
            expect($spec['paths']['/'.substr($route->uri(), 4)][strtolower($method)] ?? null, $method.' '.$route->uri())->toBeArray();
        }
    }
    $inbox = $spec['paths']['/v1/notifications']['get'];
    expect($inbox['security'])->toBe([['sessionCookie' => []]]);
    expect($inbox['responses']['429']['headers'])->toHaveKey('Retry-After');
    expect($spec['paths']['/v1/auth/login']['post']['security'] ?? [])->toBe([]);
    expect($spec['components']['schemas']['NotificationResource']['properties']['type']['type'])->toBe('string');
    expect($spec['components']['schemas']['MonthlyFinanceSummaryResource']['properties']['totals']['type'])->toBe('object');
    expect($spec['components']['schemas']['DashboardResource']['properties']['revenue']['properties']['current']['type'])->toBe('integer');
    expect($spec['components']['schemas']['OperatorActivityFeedResource']['properties']['items']['type'])->toBe('array');
    expect($spec['paths']['/v1/manager/payments/{payment}/refund']['post']['responses'])->not->toHaveKey('200');
});

test('production docs and spec reject guests and non-manager accounts', function (?string $role, bool $active) {
    $this->app->instance('env', 'production');
    if ($role !== null) {
        Sanctum::actingAs(User::factory()->create(['role' => $role, 'is_active' => $active]));
    }
    $this->getJson('/docs/api')->assertForbidden();
    $this->getJson('/docs/api.json')->assertForbidden();
})->with([[null, true], ['client', true], ['operator', true], ['manager', false]]);

test('active manager can read production API specification', function () {
    $this->app->instance('env', 'production');
    Sanctum::actingAs(User::factory()->manager()->create());
    $this->getJson('/docs/api.json')->assertOk()->assertJsonPath('openapi', '3.1.2');
});
