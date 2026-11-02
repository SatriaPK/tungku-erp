<?php

namespace Tests\Feature\Integration;

use App\Enum\SessionStatus;
use App\Enum\StageStatus;
use App\Enum\StageType;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Project;
use App\Models\RecordingSession;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\StudioSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingFlowIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_client_booking_flow(): void
    {
        $this->seed(StudioSettingSeeder::class);

        $client = User::factory()->client()->create();
        $room = Room::factory()->create();
        $package = Package::factory()->active()->create();

        $startAt = now()->addDays(2)->setTime(10, 0);
        $endAt = $startAt->copy()->addHours(2);

        // 1. Check availability
        $response = $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/client/bookings/check-availability', [
                'room_id' => $room->id,
                'start_at' => $startAt->toISOString(),
                'end_at' => $endAt->toISOString(),
            ]);

        $response->assertOk()->assertJson(['data' => ['available' => true]]);

        // 2. Hold slot
        $holdResponse = $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/client/bookings/hold', [
                'room_id' => $room->id,
                'start_at' => $startAt->toISOString(),
                'end_at' => $endAt->toISOString(),
                'package_id' => $package->id,
            ]);

        $holdResponse->assertCreated();
        $sessionId = $holdResponse->json('data.id');
        $payment = Payment::factory()->paid()->create();
        RecordingSession::query()->whereKey($sessionId)->update(['payment_id' => $payment->id]);

        // 3. List bookings
        $this->actingAs($client, 'sanctum')
            ->getJson('/api/v1/client/bookings')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // 4. View detail
        $this->actingAs($client, 'sanctum')
            ->getJson("/api/v1/client/bookings/{$sessionId}")
            ->assertOk();

        // 5. Confirm booking
        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/client/bookings/confirm', [
                'recording_session_id' => $sessionId,
            ])
            ->assertOk()
            ->assertJson(['data' => ['status' => SessionStatus::Confirmed->value]]);

        $this->assertDatabaseHas('recording_sessions', [
            'id' => $sessionId,
            'status' => SessionStatus::Confirmed->value,
        ]);
    }

    public function test_manager_can_oversee_all_bookings(): void
    {
        $this->seed(StudioSettingSeeder::class);

        $client = User::factory()->client()->create();
        $manager = User::factory()->manager()->create();
        $room = Room::factory()->create();
        $package = Package::factory()->active()->create();

        $session = RecordingSession::factory()->confirmed()->create([
            'client_id' => $client->id,
            'room_id' => $room->id,
            'package_id' => $package->id,
        ]);

        // Manager lists all bookings
        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/v1/manager/bookings')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // Manager views detail
        $this->actingAs($manager, 'sanctum')
            ->getJson("/api/v1/manager/bookings/{$session->id}")
            ->assertOk();

        // Manager cancels booking
        $this->actingAs($manager, 'sanctum')
            ->deleteJson("/api/v1/manager/bookings/{$session->id}", [
                'reason' => 'Maintenance required',
            ])
            ->assertOk()
            ->assertJson(['data' => ['status' => SessionStatus::Cancelled->value]]);
    }

    public function test_operator_sees_only_assigned_sessions(): void
    {
        $this->seed(StudioSettingSeeder::class);

        $client = User::factory()->client()->create();
        $operator = User::factory()->operator()->create();
        $project = Project::factory()->create(['client_id' => $client->id]);
        $stage = $project->stages()->create([
            'stage' => StageType::Recording,
            'sequence' => 1,
            'operator_id' => $operator->id,
            'status' => StageStatus::Ready,
        ]);
        $room = Room::factory()->create();
        $package = Package::factory()->active()->create();

        $assignedSession = RecordingSession::factory()->confirmed()->create([
            'client_id' => $client->id,
            'room_id' => $room->id,
            'package_id' => $package->id,
            'project_id' => $project->id,
            'project_stage_id' => $stage->id,
        ]);
        $unassignedProject = Project::factory()->create(['client_id' => $client->id]);
        $unassignedStage = $unassignedProject->stages()->create([
            'stage' => StageType::Recording,
            'sequence' => 1,
            'status' => StageStatus::Ready,
        ]);

        RecordingSession::factory()->confirmed()->create([
            'client_id' => $client->id,
            'room_id' => $room->id,
            'package_id' => $package->id,
            'project_id' => $unassignedProject->id,
            'project_stage_id' => $unassignedStage->id,
        ]);

        // Operator sees only assigned session
        $this->actingAs($operator, 'sanctum')
            ->getJson('/api/v1/operator/sessions')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // Operator can view assigned session
        $this->actingAs($operator, 'sanctum')
            ->getJson("/api/v1/operator/sessions/{$assignedSession->id}")
            ->assertOk();
    }

    public function test_clients_cannot_access_other_clients_bookings(): void
    {
        $this->seed(StudioSettingSeeder::class);

        $client1 = User::factory()->client()->create();
        $client2 = User::factory()->client()->create();
        $room = Room::factory()->create();
        $package = Package::factory()->active()->create();

        $session = RecordingSession::factory()->confirmed()->create([
            'client_id' => $client2->id,
            'room_id' => $room->id,
            'package_id' => $package->id,
        ]);

        // Client 1 cannot view Client 2's booking
        $this->actingAs($client1, 'sanctum')
            ->getJson("/api/v1/client/bookings/{$session->id}")
            ->assertForbidden();

        // Client 1's list should be empty
        $this->actingAs($client1, 'sanctum')
            ->getJson('/api/v1/client/bookings')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
