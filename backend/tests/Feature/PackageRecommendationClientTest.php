<?php

use App\Models\Package;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.recommendation.url' => 'https://python.example.test']);
    Http::preventStrayRequests();
});

test('Laravel forwards only catalog features to Python and keeps the frontend contract', function () {
    $package = Package::factory()->create(['name' => 'Studio Single']);
    Http::fake(['python.example.test/*' => Http::response([
        'data' => [['package_id' => $package->id, 'score' => 87, 'within_budget' => true]],
        'meta' => ['method' => 'standardized_nearest_neighbors', 'runtime' => 'python', 'quality_evaluated' => false],
    ])]);
    $url = '/api/v1/client/package-recommendations?'.http_build_query([
        'track_count' => 1, 'recording_hours' => 1, 'stages' => ['recording'],
    ]);
    $this->actingAs(User::factory()->client()->create())->getJson($url)->assertOk()
        ->assertJsonPath('data.0.package.id', $package->id)->assertJsonPath('data.0.score', 87)
        ->assertJsonPath('meta.runtime', 'python');
    Http::assertSent(function ($request) use ($package) {
        expect(array_keys($request['catalog'][0]))->toBe(['id', 'name', 'track_count', 'recording_hours', 'price', 'stages']);
        expect($request['catalog'][0]['id'])->toBe($package->id);
        expect($request->data())->not->toHaveKeys(['user', 'email', 'password']);

        return $request->url() === 'https://python.example.test/v1/recommend';
    });
});

test('Python outage returns an honest 503 without local ML fallback', function () {
    Http::fake(['*' => Http::response([], 503)]);
    $this->actingAs(User::factory()->client()->create())
        ->getJson('/api/v1/client/package-recommendations?track_count=1&recording_hours=0&stages[]=mixing')
        ->assertStatus(503)->assertJsonMissingPath('data');
});

test('unknown package IDs from the service are rejected', function () {
    Http::fake(['*' => Http::response(['data' => [['package_id' => 999999, 'score' => 100, 'within_budget' => true]], 'meta' => []])]);
    $this->actingAs(User::factory()->client()->create())
        ->getJson('/api/v1/client/package-recommendations?track_count=1&recording_hours=0&stages[]=mixing')
        ->assertStatus(503);
});

test('training command calls the Python endpoint', function () {
    Http::fake(['*' => Http::response(['meta' => ['runtime' => 'python'], 'report' => ['sample_count' => 2]])]);
    $this->artisan('ml:train-package-recommendations')->assertSuccessful();
    Http::assertSent(fn ($request) => $request->url() === 'https://python.example.test/v1/train');
});
