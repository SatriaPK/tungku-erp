<?php

use App\Enum\ProjectStatus;
use App\Enum\StageStatus;
use App\Enum\StageType;
use App\Models\BasePrice;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\RecordingSession;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\StudioSettingSeeder;

function recordingExtensionEndpointFixture(): array
{
    test()->seed(StudioSettingSeeder::class);

    $client = User::factory()->client()->create();
    $operator = User::factory()->operator()->create();
    $project = Project::factory()->create([
        'client_id' => $client->id,
        'status' => ProjectStatus::InProgress,
    ]);
    $stage = ProjectStage::query()->create([
        'project_id' => $project->id,
        'stage' => StageType::Recording,
        'sequence' => 1,
        'operator_id' => $operator->id,
        'status' => StageStatus::InProgress,
        'started_at' => now()->subHour(),
    ]);
    $room = Room::factory()->create();

    BasePrice::query()->create([
        'stage' => StageType::Recording,
        'unit' => 'hour',
        'price' => 150_000,
    ]);

    return [$operator, $stage, $room];
}

function extensionPayload(Room $room, array $overrides = []): array
{
    return array_merge([
        'room_id' => $room->id,
        'start_at' => now()->addDay()->setTime(10, 0)->toIso8601String(),
        'end_at' => now()->addDay()->setTime(11, 1)->toIso8601String(),
    ], $overrides);
}

test('assigned operator can create extension with safe response and computed fee', function () {
    [$operator, $stage, $room] = recordingExtensionEndpointFixture();

    $this->actingAs($operator)
        ->postJson("/api/v1/operator/stages/{$stage->id}/recording-extensions", extensionPayload($room))
        ->assertCreated()
        ->assertJsonPath('data.status', 'confirmed')
        ->assertJsonPath('data.is_extension', true)
        ->assertJsonPath('data.extra_fee', 300000)
        ->assertJsonPath('data.project_stage_id', $stage->id)
        ->assertJsonMissingPath('data.requested_by')
        ->assertJsonMissingPath('data.project_id');

    expect(RecordingSession::query()->count())->toBe(1);
});

test('extension endpoint rejects invalid or forged payload fields', function () {
    [$operator, $stage, $room] = recordingExtensionEndpointFixture();
    $payload = extensionPayload($room, [
        'status' => 'cancelled',
        'extra_fee' => 0,
        'is_extension' => false,
        'requested_by' => $operator->id + 1,
        'project_id' => $stage->project_id + 1,
        'tariff' => 1,
    ]);

    $this->actingAs($operator)
        ->postJson("/api/v1/operator/stages/{$stage->id}/recording-extensions", $payload)
        ->assertCreated()
        ->assertJsonMissingPath('data.requested_by')
        ->assertJsonMissingPath('data.project_id');

    $session = RecordingSession::query()->sole();
    expect($session->status->value)->toBe('confirmed')
        ->and($session->extra_fee)->toBe(300000)
        ->and($session->is_extension)->toBeTrue()
        ->and($session->requested_by)->toBe($operator->id)
        ->and($session->project_id)->toBe($stage->project_id);
});

test('only active assigned operators can request an extension', function () {
    [, $stage, $room] = recordingExtensionEndpointFixture();
    $endpoint = "/api/v1/operator/stages/{$stage->id}/recording-extensions";
    $payload = extensionPayload($room);

    $this->postJson($endpoint, $payload)->assertUnauthorized();
    $this->actingAs(User::factory()->operator()->inactive()->create())->postJson($endpoint, $payload)->assertForbidden();
    $this->actingAs(User::factory()->manager()->create())->postJson($endpoint, $payload)->assertForbidden();
    $this->actingAs(User::factory()->client()->create())->postJson($endpoint, $payload)->assertForbidden();
    $this->actingAs(User::factory()->operator()->create())->postJson($endpoint, $payload)->assertForbidden();

    expect(RecordingSession::query()->count())->toBe(0);
});

test('extension endpoint validates stage id, required fields, room id, and slot conflicts', function () {
    [$operator, $stage, $room] = recordingExtensionEndpointFixture();
    $endpoint = "/api/v1/operator/stages/{$stage->id}/recording-extensions";

    $this->actingAs($operator)->postJson($endpoint, [])->assertUnprocessable();
    $this->postJson('/api/v1/operator/stages/999999/recording-extensions', extensionPayload($room))->assertNotFound();

    $payload = extensionPayload($room);
    $this->postJson($endpoint, $payload)->assertCreated();
    $this->postJson($endpoint, $payload)->assertUnprocessable()->assertJsonValidationErrors('slot');

    expect(RecordingSession::query()->count())->toBe(1);
});
