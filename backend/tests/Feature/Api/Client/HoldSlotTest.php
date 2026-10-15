<?php

use App\Enum\OfferSide;
use App\Enum\QuotationStatus;
use App\Enum\SessionStatus;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\RecordingSession;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\StudioSettingSeeder;

beforeEach(function () {
    $this->seed(StudioSettingSeeder::class);
    $this->client = User::factory()->client()->create();
    $this->room = Room::factory()->create();
    $this->package = Package::factory()->active()->create();
});

test('successfully holds available slot', function () {
    Carbon::setTestNow('2026-10-10 08:00:00 Asia/Jakarta');

    $response = $this->actingAs($this->client)
        ->postJson('/api/v1/client/bookings/hold', [
            'room_id' => $this->room->id,
            'start_at' => '2026-10-11 10:00:00', 'end_at' => '2026-10-11 12:00:00',
            'package_id' => $this->package->id,
        ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'id',
                'status',
                'status_label',
                'start_at',
                'end_at',
                'held_until',
                'room',
                'client',
            ],
        ])
        ->assertJsonPath('data.status', 'held')
        ->assertJsonPath('data.room.id', $this->room->id)
        ->assertJsonPath('data.client.id', $this->client->id);

    // Verify database
    $this->assertDatabaseHas('recording_sessions', [
        'room_id' => $this->room->id,
        'client_id' => $this->client->id,
        'status' => SessionStatus::Held->value,
    ]);

    // Verify held_until is approximately 15 minutes from now
    $session = RecordingSession::latest()->first();
    expect($session->held_until)
        ->toBeInstanceOf(Carbon::class)
        ->and($session->held_until->diffInMinutes(Carbon::now(), true))->toBeBetween(14, 16);

    Carbon::setTestNow();
});

test('accepted quotation checkout creates a project, session, and invoice at agreed price', function () {
    Carbon::setTestNow('2026-10-10 08:00:00 Asia/Jakarta');
    $quotation = Quotation::create([
        'client_id' => $this->client->id,
        'track_count' => 2,
        'recording_hours' => 3,
        'include_recording' => true,
        'include_editing' => true,
        'include_mixing' => false,
        'include_mastering' => true,
        'status' => QuotationStatus::Accepted,
        'auto_price' => 900000,
        'agreed_price' => 1250000,
    ]);
    $quotation->offers()->create([
        'offered_by' => $this->client->id,
        'side' => OfferSide::Client,
        'price' => 1250000,
    ]);

    $response = $this->actingAs($this->client)->postJson('/api/v1/client/bookings/hold', [
        'room_id' => $this->room->id,
        'start_at' => '2026-10-11 10:00:00', 'end_at' => '2026-10-11 12:00:00',
        'quotation_id' => $quotation->id,
    ]);

    $response->assertCreated()->assertJsonPath('data.quotation_id', $quotation->id);
    $session = RecordingSession::findOrFail($response->json('data.id'));
    $project = Project::findOrFail($session->project_id);
    $invoice = Invoice::where('project_id', $project->id)->with('items')->firstOrFail();

    expect($project->quotation_id)->toBe($quotation->id)
        ->and($project->name)->toBe('Penawaran Kustom #'.$quotation->id)
        ->and($project->package_snapshot['name'])->toBe('Penawaran Kustom #'.$quotation->id)
        ->and($project->price)->toBe(1250000)
        ->and($project->package_snapshot['source'])->toBe('quotation')
        ->and($project->tracks)->toHaveCount(2)
        ->and($project->stages)->toHaveCount(3)
        ->and($invoice->total)->toBe(1250000)
        ->and($invoice->items->first()->unit_price)->toBe(1250000);

    Carbon::setTestNow();
});

test('quotation booking rejects unaccepted, foreign, and already used quotations', function () {
    $quotation = Quotation::create([
        'client_id' => $this->client->id,
        'track_count' => 1,
        'recording_hours' => 0,
        'include_recording' => false,
        'include_editing' => true,
        'status' => QuotationStatus::AwaitingStudio,
        'auto_price' => 100000,
    ]);

    $payload = ['room_id' => $this->room->id, 'start_at' => '2026-10-11 10:00', 'end_at' => '2026-10-11 12:00', 'quotation_id' => $quotation->id];
    $this->actingAs($this->client)->postJson('/api/v1/client/bookings/hold', $payload)->assertUnprocessable()->assertJsonValidationErrors('quotation_id');

    $quotation->update(['status' => QuotationStatus::Accepted, 'agreed_price' => 500000]);
    $quotation->project()->create([
        'code' => 'USED-QTN', 'client_id' => $this->client->id, 'name' => 'Used',
        'package_snapshot' => [], 'price' => 500000, 'po_amount' => 500000,
    ]);
    $this->actingAs($this->client)->postJson('/api/v1/client/bookings/hold', $payload)->assertUnprocessable()->assertJsonValidationErrors('quotation_id');

    $foreign = User::factory()->client()->create();
    $quotation->update(['client_id' => $foreign->id]);
    $this->actingAs($this->client)->postJson('/api/v1/client/bookings/hold', $payload)->assertUnprocessable()->assertJsonValidationErrors('quotation_id');
});

test('returns RecordingSessionResource with correct structure', function () {
    Carbon::setTestNow('2026-10-10 08:00:00 Asia/Jakarta');

    $response = $this->actingAs($this->client)
        ->postJson('/api/v1/client/bookings/hold', [
            'room_id' => $this->room->id,
            'start_at' => '2026-10-11 10:00:00', 'end_at' => '2026-10-11 12:00:00',
            'package_id' => $this->package->id,
        ]);

    $response->assertCreated()
        ->assertJson([
            'data' => [
                'status' => 'held',
                'status_label' => 'On Hold',
                'room' => [
                    'id' => $this->room->id,
                    'name' => $this->room->name,
                ],
                'client' => [
                    'id' => $this->client->id,
                    'name' => $this->client->name,
                    'email' => $this->client->email,
                ],
            ],
        ]);
});

test('conflicting booking returns 422 with clear error', function () {
    Carbon::setTestNow('2026-10-10 08:00:00 Asia/Jakarta');

    // Create existing confirmed session
    RecordingSession::factory()
        ->for($this->room)
        ->confirmed()
        ->timeRange('2026-10-11 10:00:00', '2026-10-11 12:00:00')
        ->create();

    $response = $this->actingAs($this->client)
        ->postJson('/api/v1/client/bookings/hold', [
            'room_id' => $this->room->id,
            'start_at' => '2026-10-11 11:00:00',
            'end_at' => '2026-10-11 13:00:00',
            'package_id' => $this->package->id,
        ]);

    $response->assertStatus(422)
        ->assertJson([
            'message' => 'The selected time slot is unavailable.',
            'errors' => [
                'start_at' => ['Slot is not available: Time slot conflicts with an existing booking or active hold.'],
            ],
        ]);
});

test('active hold blocks new hold request', function () {
    Carbon::setTestNow('2026-10-10 08:00:00 Asia/Jakarta');

    // Create existing held session (not expired)
    RecordingSession::factory()
        ->for($this->room)
        ->held(60)
        ->timeRange('2026-10-11 10:00:00', '2026-10-11 12:00:00')
        ->create();

    $response = $this->actingAs($this->client)
        ->postJson('/api/v1/client/bookings/hold', [
            'room_id' => $this->room->id,
            'start_at' => '2026-10-11 11:00:00',
            'end_at' => '2026-10-11 13:00:00',
            'package_id' => $this->package->id,
        ]);

    $response->assertStatus(422)
        ->assertJsonFragment([
            'message' => 'The selected time slot is unavailable.',
        ]);
});

test('outside operating hours returns 422', function () {
    Carbon::setTestNow('2026-10-10 08:00:00 Asia/Jakarta');

    $response = $this->actingAs($this->client)
        ->postJson('/api/v1/client/bookings/hold', [
            'room_id' => $this->room->id,
            'start_at' => '2026-10-11 08:00:00',
            'end_at' => '2026-10-11 10:00:00',
            'package_id' => $this->package->id,
        ]);

    $response->assertStatus(422)
        ->assertJsonFragment([
            'message' => 'The selected time slot is unavailable.',
        ]);
});

test('inactive package returns 422', function () {
    $inactivePackage = Package::factory()->inactive()->create();

    $response = $this->actingAs($this->client)
        ->postJson('/api/v1/client/bookings/hold', [
            'room_id' => $this->room->id,
            'start_at' => '2026-10-11 10:00:00', 'end_at' => '2026-10-11 12:00:00',
            'package_id' => $inactivePackage->id,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['package_id'])
        ->assertJsonFragment([
            'package_id' => ['The selected package is not available.'],
        ]);
});

test('can hold slot with project_id', function () {
    Carbon::setTestNow('2026-10-10 08:00:00 Asia/Jakarta');

    $project = Project::create([
        'code' => 'HOLD-001',
        'client_id' => $this->client->id,
        'name' => 'Hold Project',
        'price' => 1000000,
        'po_amount' => 1000000,
        'status' => 'awaiting_assignment',
        'package_snapshot' => [],
    ]);

    $response = $this->actingAs($this->client)
        ->postJson('/api/v1/client/bookings/hold', [
            'room_id' => $this->room->id,
            'start_at' => '2026-10-11 10:00:00', 'end_at' => '2026-10-11 12:00:00',
            'package_id' => $this->package->id,
            'project_id' => $project->id,
        ]);

    $response->assertCreated();

    $this->assertDatabaseHas('recording_sessions', [
        'client_id' => $this->client->id,
        'project_id' => $project->id,
        'status' => SessionStatus::Held->value,
    ]);
});

test('project_id must belong to authenticated client', function () {
    $otherClient = User::factory()->client()->create();
    $otherProject = Project::create([
        'code' => 'HOLD-002',
        'client_id' => $otherClient->id,
        'name' => 'Other Project',
        'price' => 1000000,
        'po_amount' => 1000000,
        'status' => 'awaiting_assignment',
        'package_snapshot' => [],
    ]);

    $response = $this->actingAs($this->client)
        ->postJson('/api/v1/client/bookings/hold', [
            'room_id' => $this->room->id,
            'start_at' => '2026-10-11 10:00:00', 'end_at' => '2026-10-11 12:00:00',
            'package_id' => $this->package->id,
            'project_id' => $otherProject->id,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['project_id'])
        ->assertJsonFragment([
            'project_id' => ['The selected project does not belong to you.'],
        ]);
});

test('can hold same slot after previous hold expires', function () {
    Carbon::setTestNow('2026-10-10 08:00:00 Asia/Jakarta');

    // Create expired hold
    RecordingSession::factory()
        ->for($this->room)
        ->held(-30) // Expired 30 minutes ago
        ->timeRange('2026-10-11 10:00:00', '2026-10-11 12:00:00')
        ->create();

    Carbon::setTestNow('2026-10-10 08:00:00');

    $response = $this->actingAs($this->client)
        ->postJson('/api/v1/client/bookings/hold', [
            'room_id' => $this->room->id,
            'start_at' => '2026-10-11 10:00:00', 'end_at' => '2026-10-11 12:00:00',
            'package_id' => $this->package->id,
        ]);

    $response->assertCreated();

    Carbon::setTestNow();
});

test('room_id is required', function () {
    $response = $this->actingAs($this->client)
        ->postJson('/api/v1/client/bookings/hold', [
            'start_at' => '2026-10-11 10:00:00', 'end_at' => '2026-10-11 12:00:00',
            'package_id' => $this->package->id,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['room_id']);
});

test('room_id must exist', function () {
    $response = $this->actingAs($this->client)
        ->postJson('/api/v1/client/bookings/hold', [
            'room_id' => 99999,
            'start_at' => '2026-10-11 10:00:00', 'end_at' => '2026-10-11 12:00:00',
            'package_id' => $this->package->id,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['room_id']);
});

test('start_at is required', function () {
    $response = $this->actingAs($this->client)
        ->postJson('/api/v1/client/bookings/hold', [
            'room_id' => $this->room->id,
            'end_at' => '2026-10-11 12:00:00',
            'package_id' => $this->package->id,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['start_at']);
});

test('start_at must be in the future', function () {
    Carbon::setTestNow('2026-10-10 08:00:00 Asia/Jakarta');

    $response = $this->actingAs($this->client)
        ->postJson('/api/v1/client/bookings/hold', [
            'room_id' => $this->room->id,
            'start_at' => '2026-10-09 16:00:00',
            'end_at' => '2026-10-09 18:00:00',
            'package_id' => $this->package->id,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['start_at']);

    Carbon::setTestNow();
});

test('end_at is required', function () {
    $response = $this->actingAs($this->client)
        ->postJson('/api/v1/client/bookings/hold', [
            'room_id' => $this->room->id,
            'start_at' => '2026-10-11 10:00:00',
            'package_id' => $this->package->id,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['end_at']);
});

test('end_at must be after start_at', function () {
    $response = $this->actingAs($this->client)
        ->postJson('/api/v1/client/bookings/hold', [
            'room_id' => $this->room->id,
            'start_at' => '2026-10-11 12:00:00',
            'end_at' => '2026-10-11 10:00:00',
            'package_id' => $this->package->id,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['end_at']);
});

test('package_id is required', function () {
    $response = $this->actingAs($this->client)
        ->postJson('/api/v1/client/bookings/hold', [
            'room_id' => $this->room->id,
            'start_at' => '2026-10-11 10:00:00', 'end_at' => '2026-10-11 12:00:00',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['package_id']);
});

test('package_id must exist', function () {
    $response = $this->actingAs($this->client)
        ->postJson('/api/v1/client/bookings/hold', [
            'room_id' => $this->room->id,
            'start_at' => '2026-10-11 10:00:00', 'end_at' => '2026-10-11 12:00:00',
            'package_id' => 99999,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['package_id']);
});

test('duration must be at least 1 hour', function () {
    $response = $this->actingAs($this->client)
        ->postJson('/api/v1/client/bookings/hold', [
            'room_id' => $this->room->id,
            'start_at' => '2026-10-11 10:00:00',
            'end_at' => '2026-10-11 10:30:00',
            'package_id' => $this->package->id,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['end_at']);
});

test('duration cannot exceed 12 hours', function () {
    $response = $this->actingAs($this->client)
        ->postJson('/api/v1/client/bookings/hold', [
            'room_id' => $this->room->id,
            'start_at' => '2026-10-11 10:00:00',
            'end_at' => '2026-10-12 00:00:00',
            'package_id' => $this->package->id,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['end_at']);
});

test('exactly 1 hour duration is valid', function () {
    Carbon::setTestNow('2026-10-10 08:00:00 Asia/Jakarta');

    $response = $this->actingAs($this->client)
        ->postJson('/api/v1/client/bookings/hold', [
            'room_id' => $this->room->id,
            'start_at' => '2026-10-11 10:00:00',
            'end_at' => '2026-10-11 11:00:00',
            'package_id' => $this->package->id,
        ]);

    $response->assertCreated();
});

test('exactly 12 hours duration is valid', function () {
    Carbon::setTestNow('2026-10-10 08:00:00 Asia/Jakarta');

    $response = $this->actingAs($this->client)
        ->postJson('/api/v1/client/bookings/hold', [
            'room_id' => $this->room->id,
            'start_at' => '2026-10-11 10:00:00',
            'end_at' => '2026-10-11 22:00:00',
            'package_id' => $this->package->id,
        ]);

    $response->assertCreated();
});

test('unauthenticated request returns 401', function () {
    $response = $this->postJson('/api/v1/client/bookings/hold', [
        'room_id' => $this->room->id,
        'start_at' => '2026-10-11 10:00:00', 'end_at' => '2026-10-11 12:00:00',
        'package_id' => $this->package->id,
    ]);

    $response->assertStatus(401);
});

test('inactive user returns 403', function () {
    $inactiveUser = User::factory()->client()->inactive()->create();

    $response = $this->actingAs($inactiveUser)
        ->postJson('/api/v1/client/bookings/hold', [
            'room_id' => $this->room->id,
            'start_at' => '2026-10-11 10:00:00', 'end_at' => '2026-10-11 12:00:00',
            'package_id' => $this->package->id,
        ]);

    $response->assertStatus(403);
});
