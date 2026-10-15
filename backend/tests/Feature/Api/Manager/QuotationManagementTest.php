<?php

use App\Enum\OfferSide;
use App\Enum\QuotationResponseType;
use App\Enum\QuotationStatus;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->managerUser = User::factory()->manager()->create();
    $this->clientUser = User::factory()->client()->create();

    $this->quotation = Quotation::create([
        'client_id' => $this->clientUser->id,
        'track_count' => 2,
        'recording_hours' => 3,
        'include_recording' => true,
        'include_mixing' => true,
        'status' => QuotationStatus::AwaitingStudio,
        'auto_price' => 1250000,
    ]);

    $this->lastOffer = $this->quotation->offers()->create([
        'offered_by' => $this->clientUser->id,
        'side' => OfferSide::Client,
        'price' => 800000,
        'note' => 'Initial budget',
    ]);
});

test('manager can see paginated quotations', function () {
    $response = $this->actingAs($this->managerUser)
        ->getJson('/api/v1/manager/quotations');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->quotation->id);
});

test('manager can counter offer a quotation', function () {
    $response = $this->actingAs($this->managerUser)
        ->postJson("/api/v1/manager/quotations/{$this->quotation->id}/respond", [
            'response_type' => QuotationResponseType::Counter->value,
            'latest_offer_id' => $this->lastOffer->id,
            'price' => 1100000,
            'note' => 'This is the best we can do',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.status', QuotationStatus::AwaitingClient->value)
        ->assertJsonPath('data.latest_offer.price', 1100000);
});

test('manager cannot respond when it is not the studio turn', function () {
    $this->quotation->update(['status' => QuotationStatus::AwaitingClient]);

    $this->actingAs($this->managerUser)
        ->postJson("/api/v1/manager/quotations/{$this->quotation->id}/respond", [
            'response_type' => QuotationResponseType::Counter->value,
            'latest_offer_id' => $this->lastOffer->id,
            'price' => 1100000,
        ])
        ->assertStatus(400);

    expect($this->quotation->offers()->count())->toBe(1)
        ->and($this->quotation->fresh()->status)->toBe(QuotationStatus::AwaitingClient);
});

test('manager cannot change an accepted quotation or add offers afterward', function () {
    $this->quotation->update([
        'status' => QuotationStatus::Accepted,
        'agreed_price' => 800000,
    ]);

    $this->actingAs($this->managerUser)
        ->postJson("/api/v1/manager/quotations/{$this->quotation->id}/respond", [
            'response_type' => QuotationResponseType::Counter->value,
            'latest_offer_id' => $this->lastOffer->id,
            'price' => 1000000,
        ])
        ->assertStatus(400);

    expect($this->quotation->offers()->count())->toBe(1)
        ->and($this->quotation->fresh()->status)->toBe(QuotationStatus::Accepted)
        ->and($this->quotation->fresh()->agreed_price)->toBe(800000);
});

test('client can see own quotations', function () {
    $response = $this->actingAs($this->clientUser)
        ->getJson('/api/v1/client/quotations');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->quotation->id);
});
