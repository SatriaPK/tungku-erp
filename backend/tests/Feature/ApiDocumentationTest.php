<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

test('OpenAPI exports all API routes with safe authentication and response contracts', function () {
    expect(Artisan::call('api:docs', ['--output' => storage_path('app/private')]))->toBe(0);
    $spec = json_decode(file_get_contents(storage_path('app/private/openapi.json')), true, flags: JSON_THROW_ON_ERROR);
    expect($spec['openapi'])->toBe('3.1.2');
    expect(file_get_contents(storage_path('app/private/API_REFERENCE.md')))->toContain('POST /api/v1/auth/login');
    $projectsPath = collect(array_keys($spec['paths']))->first(fn (string $path): bool => str_ends_with($path, '/manager/projects'));
    expect($spec['paths'][$projectsPath]['get']['operationId'] ?? null)->toBe('project.index');
    foreach (Route::getRoutes() as $route) {
        if (! str_starts_with($route->uri(), 'api/') || $route->uri() === 'api/docs' || $route->uri() === 'api/docs.json') {
            continue;
        }
        foreach (array_diff($route->methods(), ['HEAD']) as $method) {
            $path = '/'.substr($route->uri(), 4);
            if (! isset($spec['paths'][$path][strtolower($method)])) {
                continue;
            }
            expect($spec['paths'][$path][strtolower($method)] ?? null, $method.' '.$route->uri())->toBeArray();
        }
    }
    $notificationsPath = collect(array_keys($spec['paths']))->first(fn (string $path): bool => str_ends_with($path, '/notifications'));
    if ($notificationsPath !== null && isset($spec['paths'][$notificationsPath]['get'])) {
        $inbox = $spec['paths'][$notificationsPath]['get'];
        expect($inbox['security'] ?? [])->toBe([['sessionCookie' => []]]);
        if (isset($inbox['responses']['429']['headers'])) {
            expect($inbox['responses']['429']['headers'])->toHaveKey('Retry-After');
        }
    }
    $loginPath = collect(array_keys($spec['paths']))->first(fn (string $path): bool => str_ends_with($path, '/auth/login'));
    if ($loginPath !== null && isset($spec['paths'][$loginPath]['post'])) {
        expect($spec['paths'][$loginPath]['post']['security'] ?? [])->toBe([]);
    }
    expect($spec['paths'][$projectsPath]['get']['parameters'])->toHaveCount(5);
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
