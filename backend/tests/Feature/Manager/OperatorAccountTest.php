<?php

use App\Enum\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('manager can create and update an operator without changing roles', function () {
    $manager = User::factory()->create(['role' => Role::Manager]);
    $this->actingAs($manager)->postJson('/api/v1/manager/operator-accounts', [
        'name' => 'Operator Baru', 'email' => 'OP@studio.test', 'phone' => '081234567890',
        'password' => 'operator-secret', 'password_confirmation' => 'operator-secret', 'role' => 'manager',
    ])->assertCreated()->assertJsonPath('data.role', 'operator');
    $operator = User::where('email', 'op@studio.test')->firstOrFail();
    expect($operator->phone)->toBe('6281234567890');
    $this->putJson("/api/v1/manager/operator-accounts/{$operator->id}", [
        'name' => 'Operator Diperbarui', 'email' => $operator->email, 'phone' => $operator->phone, 'is_active' => false,
    ])->assertOk()->assertJsonPath('data.name', 'Operator Diperbarui');
    expect($operator->refresh()->role)->toBe(Role::Operator);
    expect($operator->is_active)->toBeFalse();
    expect(Hash::check('operator-secret', $operator->password))->toBeTrue();
    $this->getJson('/api/v1/manager/operator-accounts')->assertOk()
        ->assertJsonFragment(['name' => 'Operator Diperbarui', 'is_active' => false]);
});

test('client cannot manage operator accounts', function () {
    $client = User::factory()->create(['role' => Role::Client]);
    $this->actingAs($client)->postJson('/api/v1/manager/operator-accounts', [
        'name' => 'Fake', 'email' => 'fake@studio.test', 'phone' => '081234567890',
        'password' => 'operator-secret', 'password_confirmation' => 'operator-secret',
    ])->assertForbidden();
});
