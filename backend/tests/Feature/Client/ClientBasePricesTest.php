<?php

use App\Models\User;
use Database\Seeders\BasePriceSeeder;

test('client can read current production rates but cannot edit manager prices', function () {
    $this->seed(BasePriceSeeder::class);
    $client = User::factory()->create();

    $this->actingAs($client)->getJson('/api/v1/client/base-prices')
        ->assertOk()
        ->assertJsonCount(4, 'data')
        ->assertJsonFragment(['stage' => 'recording', 'price' => 150000])
        ->assertJsonFragment(['stage' => 'mixing', 'price' => 400000]);

    $this->putJson('/api/v1/manager/base-prices', ['prices' => []])->assertForbidden();
});

test('production rates require an authenticated active account', function () {
    $this->getJson('/api/v1/client/base-prices')->assertUnauthorized();
    $client = User::factory()->create(['is_active' => false]);
    $this->actingAs($client)->getJson('/api/v1/client/base-prices')->assertForbidden();
});
