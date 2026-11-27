<?php

use App\Models\Invoice;
use App\Models\User;

test('manager workspace loads client choices and invoices', function () {
    $manager = User::factory()->manager()->create();
    $client = User::factory()->client()->create();
    $invoice = Invoice::factory()->create(['client_id' => $client->id]);

    $this->actingAs($manager)->getJson('/api/v1/manager/clients')->assertOk()
        ->assertJsonFragment(['id' => $client->id, 'name' => $client->name]);
    $this->getJson('/api/v1/manager/invoices')->assertOk()
        ->assertJsonFragment(['id' => $invoice->id]);
});

test('manager can edit and remove an unassigned operator', function () {
    $manager = User::factory()->manager()->create();
    $operator = User::factory()->operator()->create();

    $this->actingAs($manager)->putJson("/api/v1/manager/operators/{$operator->id}", [
        'name' => 'Operator Baru', 'email' => $operator->email, 'phone' => '081234567890',
    ])->assertOk()->assertJsonPath('data.name', 'Operator Baru');
    expect($operator->refresh()->phone)->toBe('6281234567890');

    $this->deleteJson("/api/v1/manager/operators/{$operator->id}")->assertNoContent();
});
