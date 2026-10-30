<?php

use App\Models\Package;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('returns package totals grouped by included production stage', function () {
    Sanctum::actingAs(User::factory()->manager()->create());

    Package::factory()->create([
        'include_recording' => true,
        'include_editing' => true,
        'include_mixing' => false,
        'include_mastering' => false,
        'is_active' => true,
    ]);
    Package::factory()->create([
        'include_recording' => false,
        'include_editing' => true,
        'include_mixing' => true,
        'include_mastering' => true,
        'is_active' => true,
    ]);
    Package::factory()->create([
        'include_recording' => true,
        'include_editing' => false,
        'include_mixing' => false,
        'include_mastering' => false,
        'is_active' => false,
    ]);

    $this->getJson('/api/v1/manager/packages/summary')->assertOk()
        ->assertExactJson(['data' => [
            'total' => 2,
            'by_stage' => ['recording' => 1, 'editing' => 2, 'mixing' => 1, 'mastering' => 1],
        ]]);
});

it('forbids package summaries to non-manager users', function () {
    Sanctum::actingAs(User::factory()->operator()->create());

    $this->getJson('/api/v1/manager/packages/summary')->assertForbidden();
});
