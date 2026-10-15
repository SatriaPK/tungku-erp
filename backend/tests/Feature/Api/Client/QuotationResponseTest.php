<?php

use App\Enum\OfferSide;
use App\Enum\QuotationResponseType;
use App\Enum\QuotationStatus;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->clientUser = User::factory()->client()->create();
    $this->managerUser = User::factory()->manager()->create();

    $this->quotation = Quotation::create([
        'client_id' => $this->clientUser->id,
        'track_count' => 2,
        'recording_hours' => 3,
        'include_recording' => true,
        'include_mixing' => true,
        'status' => QuotationStatus::AwaitingClient,
        'auto_price' => 1250000,
    ]);

    $this->lastOffer = $this->quotation->offers()->create([
        'offered_by' => $this->managerUser->id,
        'side' => OfferSide::Studio,
        'price' => 1100000,
        'note' => 'Ini net mas',
    ]);
});

test('client can accept an offer', function () {
    $response = $this->actingAs($this->clientUser)
        ->postJson("/api/v1/client/quotations/{$this->quotation->id}/respond", [
            'response_type' => QuotationResponseType::Accept->value,
            'latest_offer_id' => $this->lastOffer->id,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.status', QuotationStatus::Accepted->value)
        ->assertJsonPath('data.agreed_price', 1100000);

    $this->assertDatabaseHas('quotations', [
        'id' => $this->quotation->id,
        'status' => QuotationStatus::Accepted->value,
        'closed_by' => $this->clientUser->id,
        'agreed_price' => 1100000,
    ]);
});

test('client cannot respond if out of turn', function () {
    $this->quotation->update(['status' => QuotationStatus::AwaitingStudio]);

    $response = $this->actingAs($this->clientUser)
        ->postJson("/api/v1/client/quotations/{$this->quotation->id}/respond", [
            'response_type' => QuotationResponseType::Accept->value,
            'latest_offer_id' => $this->lastOffer->id,
        ]);
    $response->assertStatus(400);
});

test('client cannot respond to a stale offer id', function () {
    $this->quotation->offers()->create([
        'offered_by' => $this->clientUser->id,
        'side' => OfferSide::Client,
        'price' => 1000000,
    ]);

    $this->actingAs($this->clientUser)
        ->postJson("/api/v1/client/quotations/{$this->quotation->id}/respond", [
            'response_type' => QuotationResponseType::Accept->value,
            'latest_offer_id' => $this->lastOffer->id,
        ])
        ->assertStatus(400);

    expect($this->quotation->fresh()->status)->toBe(QuotationStatus::AwaitingClient);
});

test('accepted quotation cannot be changed by a subsequent response', function () {
    $this->actingAs($this->clientUser)
        ->postJson("/api/v1/client/quotations/{$this->quotation->id}/respond", [
            'response_type' => QuotationResponseType::Accept->value,
            'latest_offer_id' => $this->lastOffer->id,
        ])
        ->assertOk();

    $this->actingAs($this->clientUser)
        ->postJson("/api/v1/client/quotations/{$this->quotation->id}/respond", [
            'response_type' => QuotationResponseType::Decline->value,
            'latest_offer_id' => $this->lastOffer->id,
        ])
        ->assertStatus(400);

    expect($this->quotation->fresh()->status)->toBe(QuotationStatus::Accepted)
        ->and($this->quotation->fresh()->agreed_price)->toBe(1100000)
        ->and($this->quotation->offers()->count())->toBe(1);
});

test('declined quotation cannot be changed by a subsequent response', function () {
    $this->actingAs($this->clientUser)
        ->postJson("/api/v1/client/quotations/{$this->quotation->id}/respond", [
            'response_type' => QuotationResponseType::Decline->value,
            'latest_offer_id' => $this->lastOffer->id,
        ])
        ->assertOk();

    $this->actingAs($this->clientUser)
        ->postJson("/api/v1/client/quotations/{$this->quotation->id}/respond", [
            'response_type' => QuotationResponseType::Counter->value,
            'latest_offer_id' => $this->lastOffer->id,
            'price' => 1000000,
        ])
        ->assertStatus(400);

    expect($this->quotation->fresh()->status)->toBe(QuotationStatus::Declined)
        ->and($this->quotation->offers()->count())->toBe(1);
});
