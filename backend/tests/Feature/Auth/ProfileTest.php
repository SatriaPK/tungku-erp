<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('updates account details without changing role or password', function () {
    $manager = User::factory()->create(['role' => 'manager', 'password' => 'old-password']);
    $this->actingAs($manager)->putJson('/api/v1/auth/profile', [
        'name' => 'Manager Baru', 'email' => 'NEW@studio.test', 'phone' => '081234567890', 'role' => 'client',
    ])->assertOk()->assertJsonPath('data.name', 'Manager Baru')->assertJsonPath('data.email', 'new@studio.test')->assertJsonPath('data.role', 'manager');
    $manager->refresh();
    expect($manager->phone)->toBe('6281234567890');
    expect(Hash::check('old-password', $manager->password))->toBeTrue();
});

it('requires current password before changing password', function () {
    $operator = User::factory()->create(['role' => 'operator', 'password' => 'old-password']);
    $details = ['name' => $operator->name, 'email' => $operator->email, 'phone' => $operator->phone,
        'password' => 'new-password', 'password_confirmation' => 'new-password'];
    $this->actingAs($operator)->putJson('/api/v1/auth/profile', $details + ['current_password' => 'wrong'])
        ->assertUnprocessable()->assertJsonValidationErrors('current_password');
    $this->putJson('/api/v1/auth/profile', $details + ['current_password' => 'old-password'])->assertOk();
    expect(Hash::check('new-password', $operator->refresh()->password))->toBeTrue();
});

it('rejects duplicate email and unauthenticated edits', function () {
    $client = User::factory()->create();
    $other = User::factory()->create();
    $this->putJson('/api/v1/auth/profile', ['name' => 'A'])->assertUnauthorized();
    $this->actingAs($client)->putJson('/api/v1/auth/profile', ['name' => 'A', 'email' => $other->email, 'phone' => $client->phone])
        ->assertUnprocessable()->assertJsonValidationErrors('email');
});
