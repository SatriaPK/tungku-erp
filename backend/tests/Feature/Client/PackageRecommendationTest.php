<?php

use App\Models\Package;
use App\Models\User;

function recommendationUrl(array $overrides = []): string
{
    return '/api/v1/client/package-recommendations?'.http_build_query(array_merge([
        'track_count' => 2, 'recording_hours' => 4,
        'stages' => ['recording', 'mixing'], 'budget' => 2000000,
    ], $overrides));
}

test('recommendations cover requirements and prefer affordable matching capacity', function () {
    $exact = Package::factory()->create([
        'track_count' => 2, 'recording_hours' => 4, 'auto_price' => 1500000,
        'include_editing' => false, 'include_mastering' => false,
    ]);
    Package::factory()->create(['track_count' => 5, 'recording_hours' => 10, 'auto_price' => 1700000]);
    Package::factory()->create(['track_count' => 2, 'recording_hours' => 4, 'auto_price' => 2500000]);
    $inactive = Package::factory()->inactive()->create();
    $missing = Package::factory()->create(['include_mixing' => false]);
    $small = Package::factory()->create(['track_count' => 1]);
    $short = Package::factory()->create(['recording_hours' => 1]);
    $response = $this->actingAs(User::factory()->create())->getJson(recommendationUrl(['budget' => 1500000]))
        ->assertOk()->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.package.id', $exact->id)
        ->assertJsonPath('data.0.score', 100)
        ->assertJsonPath('data.0.within_budget', true)
        ->assertJsonPath('data.2.within_budget', false);
    $ids = collect($response->json('data'))->pluck('package.id');
    foreach ([$inactive, $missing, $small, $short] as $excluded) {
        expect($ids)->not->toContain($excluded->id);
    }
    expect($response->json('data.0.package'))->not->toHaveKeys(['auto_price', 'manual_price', 'created_by']);
});

test('recommendations use manual price and handle empty matches', function () {
    $package = Package::factory()->manualPrice(1000000)->create(['track_count' => 2, 'recording_hours' => 4]);
    $this->actingAs(User::factory()->create())->getJson(recommendationUrl())
        ->assertOk()->assertJsonPath('data.0.package.price', 1000000)->assertJsonPath('data.0.within_budget', true);
    $package->update(['is_active' => false]);
    $this->getJson(recommendationUrl())->assertOk()->assertJsonCount(0, 'data');
});

test('recommendations support post production without recording and no budget', function () {
    Package::factory()->create(['track_count' => 2, 'recording_hours' => 0, 'include_recording' => false]);
    $this->actingAs(User::factory()->create())->getJson(recommendationUrl([
        'recording_hours' => 0, 'stages' => ['mixing'], 'budget' => null,
    ]))->assertOk()->assertJsonCount(1, 'data');
});

test('recommendations reject invalid needs', function (array $input) {
    $this->actingAs(User::factory()->create())->getJson(recommendationUrl($input))->assertUnprocessable();
})->with([
    [['track_count' => 0]], [['stages' => []]], [['stages' => ['invalid']]],
    [['budget' => -1]], [['budget' => 1.5]], [['recording_hours' => 0]],
    [['stages' => ['mixing', 'mixing']]],
]);

test('client workspace endpoints reject other roles', function (string $role) {
    $this->actingAs(User::factory()->create(['role' => $role]));
    foreach (['packages', 'bookings', 'quotations', 'invoices', 'package-recommendations'] as $path) {
        $this->getJson('/api/v1/client/'.$path)->assertForbidden();
    }
})->with(['manager', 'operator']);

test('client cannot enter staff APIs', function () {
    $this->getJson(recommendationUrl())->assertUnauthorized();
    $client = User::factory()->create();
    $this->actingAs($client)->getJson('/api/v1/manager/packages')->assertForbidden();
    $this->getJson('/api/v1/operator/stages')->assertForbidden();
});

test('inactive accounts cannot get recommendations', function () {
    $client = User::factory()->create(['is_active' => false]);
    $this->actingAs($client)->getJson(recommendationUrl())->assertForbidden();
});
