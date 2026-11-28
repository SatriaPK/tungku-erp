<?php

use App\Enum\PaymentStatus;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use App\Services\Payment\PaymentGatewayFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->manager = User::factory()->manager()->create();
    $this->client = User::factory()->client()->create();
    $this->project = Project::factory()->for($this->client, 'client')->create();

    // Use fake gateway for all tests
    PaymentGatewayFactory::fake();
});

afterEach(function () {
    PaymentGatewayFactory::clearFake();
});

describe('Payment Listing', function () {
    it('manager can view all payments', function () {
        Payment::factory()->count(5)->create(['project_id' => $this->project->id]);

        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/manager/payments');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'order_id',
                        'amount',
                        'status',
                        'provider',
                        'client',
                        'project',
                    ],
                ],
                'meta',
            ]);
    });

    it('can filter payments by status', function () {
        Payment::factory()->create([
            'project_id' => $this->project->id,
            'status' => PaymentStatus::Paid,
        ]);
        Payment::factory()->create([
            'project_id' => $this->project->id,
            'status' => PaymentStatus::Pending,
        ]);

        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/manager/payments?status=paid');

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1);
        expect($response->json('data.0.status'))->toBe('paid');
    });

    it('can filter payments by client', function () {
        $anotherClient = User::factory()->client()->create();
        $anotherProject = Project::factory()->for($anotherClient, 'client')->create();

        Payment::factory()->create(['project_id' => $this->project->id]);
        Payment::factory()->create(['project_id' => $anotherProject->id]);

        Sanctum::actingAs($this->manager);

        $response = $this->getJson("/api/v1/manager/payments?client_id={$this->client->id}");

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1);
    });

    it('requires manager role', function () {
        Sanctum::actingAs($this->client);

        $response = $this->getJson('/api/v1/manager/payments');

        $response->assertForbidden();
    });
});

describe('Payment Detail', function () {
    it('manager can view payment detail', function () {
        $payment = Payment::factory()->create(['project_id' => $this->project->id]);

        Sanctum::actingAs($this->manager);

        $response = $this->getJson("/api/v1/manager/payments/{$payment->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'order_id',
                    'amount',
                    'status',
                    'provider',
                    'client',
                    'project',
                    'invoice',
                ],
            ]);
    });
});

describe('Confirm Manual Payment', function () {
    it('manager can confirm cash payment', function () {
        $payment = Payment::factory()->pending()->cash()->create([
            'project_id' => $this->project->id,
        ]);

        Sanctum::actingAs($this->manager);

        $response = $this->postJson("/api/v1/manager/payments/{$payment->id}/confirm", [
            'note' => 'Confirmed via bank transfer',
            'paid_at' => '2026-10-09T10:00:00',
        ]);

        $response->assertOk();
        expect($response->json('data.status'))->toBe('paid');
        expect(str_contains($response->json('data.notes'), 'Confirmed via bank transfer'))->toBeTrue();
    });

    it('cannot confirm already paid payment', function () {
        $payment = Payment::factory()->paid()->create([
            'project_id' => $this->project->id,
        ]);

        Sanctum::actingAs($this->manager);

        $response = $this->postJson("/api/v1/manager/payments/{$payment->id}/confirm", [
            'note' => 'Trying to confirm',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('errors.payment', fn ($errors) => count($errors) > 0);
    });

    it('cannot confirm online payment', function () {
        $payment = Payment::factory()->pending()->xendit()->create([
            'project_id' => $this->project->id,
        ]);

        Sanctum::actingAs($this->manager);

        $response = $this->postJson("/api/v1/manager/payments/{$payment->id}/confirm", [
            'note' => 'Trying to confirm',
        ]);

        $response->assertUnprocessable();
    });

    it('requires note', function () {
        $payment = Payment::factory()->pending()->cash()->create([
            'project_id' => $this->project->id,
        ]);

        Sanctum::actingAs($this->manager);

        $response = $this->postJson("/api/v1/manager/payments/{$payment->id}/confirm", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['note']);
    });
});

describe('Refund Payment', function () {
    it('manager can refund paid payment', function () {
        $payment = Payment::factory()->paid()->create([
            'project_id' => $this->project->id,
            'amount' => 500000,
        ]);

        Sanctum::actingAs($this->manager);

        $response = $this->postJson("/api/v1/manager/payments/{$payment->id}/refund", [
            'amount' => 500000,
            'reason' => 'Customer requested refund',
        ]);

        $response->assertOk();
        expect($response->json('data.status'))->toBe('refunded');
    });

    it('cannot refund unpaid payment', function () {
        $payment = Payment::factory()->pending()->create([
            'project_id' => $this->project->id,
        ]);

        Sanctum::actingAs($this->manager);

        $response = $this->postJson("/api/v1/manager/payments/{$payment->id}/refund", [
            'amount' => 500000,
            'reason' => 'Refund',
        ]);

        $response->assertUnprocessable();
    });

    it('requires amount and reason', function () {
        $payment = Payment::factory()->paid()->create([
            'project_id' => $this->project->id,
        ]);

        Sanctum::actingAs($this->manager);

        $response = $this->postJson("/api/v1/manager/payments/{$payment->id}/refund", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['amount', 'reason']);
    });
});

describe('Cancel Payment', function () {
    it('manager can cancel pending payment', function () {
        $payment = Payment::factory()->pending()->create([
            'project_id' => $this->project->id,
        ]);

        Sanctum::actingAs($this->manager);

        $response = $this->postJson("/api/v1/manager/payments/{$payment->id}/cancel", [
            'reason' => 'Client requested cancellation',
        ]);

        $response->assertOk();
        expect($response->json('data.status'))->toBe('cancelled');
    });

    it('cannot cancel paid payment', function () {
        $payment = Payment::factory()->paid()->create([
            'project_id' => $this->project->id,
        ]);

        Sanctum::actingAs($this->manager);

        $response = $this->postJson("/api/v1/manager/payments/{$payment->id}/cancel", [
            'reason' => 'Cancellation',
        ]);

        $response->assertUnprocessable();
    });

    it('requires reason', function () {
        $payment = Payment::factory()->pending()->create([
            'project_id' => $this->project->id,
        ]);

        Sanctum::actingAs($this->manager);

        $response = $this->postJson("/api/v1/manager/payments/{$payment->id}/cancel", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);
    });
});
