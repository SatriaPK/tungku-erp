<?php

use App\Enum\OfferSide;
use App\Enum\QuotationStatus;
use App\Enum\StageType;
use App\Models\BasePrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->clientUser = User::factory()->client()->create();

    // Seed essential base prices for test
    BasePrice::create([
        'stage' => StageType::Recording,
        'price' => 150000,
        'unit' => 'hour',
    ]);
    BasePrice::create([
        'stage' => StageType::Mixing,
        'price' => 400000,
        'unit' => 'track',
    ]);
});

test('client can submit a custom package quotation', function () {
    $payload = [
        'name' => 'Album Perdana',
        'track_count' => 2,
        'recording_hours' => 3,
        'include_recording' => true, // 3 * 150k = 450k
        'include_editing' => false,
        'include_mixing' => true, // 2 * 400k = 800k
        'include_mastering' => false,
        'notes' => 'Tolong bisa diskon dikit mas?',
        'offer_price' => 1000000, // Tawar 1 juta dari 1,250 juta auto_price
    ];

    $response = $this->actingAs($this->clientUser)
        ->postJson('/api/v1/client/quotations', $payload);

    $response->assertCreated()
        ->assertJsonPath('data.client_id', $this->clientUser->id)
        ->assertJsonPath('data.auto_price', 1250000)
        ->assertJsonPath('data.status', QuotationStatus::AwaitingStudio->value)
        ->assertJsonPath('data.latest_offer.price', 1000000)
        ->assertJsonPath('data.latest_offer.side', OfferSide::Client->value)
        ->assertJsonPath('data.latest_offer.note', 'Tolong bisa diskon dikit mas?');

    $this->assertDatabaseHas('quotations', [
        'client_id' => $this->clientUser->id,
        'track_count' => 2,
        'recording_hours' => 3,
        'auto_price' => 1250000,
        'status' => QuotationStatus::AwaitingStudio->value,
    ]);
});
