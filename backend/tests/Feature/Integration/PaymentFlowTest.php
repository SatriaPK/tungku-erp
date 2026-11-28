<?php

namespace Tests\Feature\Integration;

use App\Actions\Invoice\IssueInvoice;
use App\Enum\PriceUnit;
use App\Enum\Role;
use App\Enum\SessionStatus;
use App\Enum\StageType;
use App\Models\BasePrice;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\RecordingSession;
use App\Models\Room;
use App\Models\StudioSetting;
use App\Models\User;
use App\Services\Payment\PaymentGatewayFactory;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $client;

    protected Room $room;

    protected Package $package;

    protected function setUp(): void
    {
        parent::setUp();

        StudioSetting::create(['key' => 'operating_hours', 'value' => json_encode(['monday' => '00:00-23:59', 'tuesday' => '00:00-23:59', 'wednesday' => '00:00-23:59', 'thursday' => '00:00-23:59', 'friday' => '00:00-23:59', 'saturday' => '00:00-23:59', 'sunday' => '00:00-23:59']), 'type' => 'json']);
        StudioSetting::create(['key' => 'hold_duration_minutes', 'value' => '15', 'type' => 'integer']);
        StudioSetting::create(['key' => 'payment.expiration_hours', 'value' => '24', 'type' => 'integer']);
        BasePrice::create([
            'stage' => StageType::Recording->value,
            'unit' => PriceUnit::Hour->value,
            'price' => 150000,
        ]);

        $this->client = User::factory()->create(['role' => Role::Client]);
        $this->room = Room::factory()->create(['is_active' => true]);
        $this->package = Package::factory()->create([
            'is_active' => true,
            'is_price_manual' => true,
            'manual_price' => 500000,
            'recording_hours' => 2,
            'track_count' => 1,
        ]);

        config(['payment.xendit.secret_key' => 'dummy']);
    }

    public function test_booking_workflow_creates_invoice_and_can_initiate_payment(): void
    {
        $startAt = Carbon::now()->addDays(2)->setHour(10)->setMinute(0);
        $endAt = $startAt->copy()->addHours(2);

        $response = $this->actingAs($this->client)->postJson('/api/v1/client/bookings/hold', [
            'room_id' => $this->room->id,
            'start_at' => $startAt->toIso8601String(),
            'end_at' => $endAt->toIso8601String(),
            'package_id' => $this->package->id,
        ]);

        $response->assertStatus(201);
        $sessionId = $response->json('data.id');

        $session = RecordingSession::find($sessionId);
        $this->assertEquals(SessionStatus::Held, $session->status);

        $this->assertDatabaseHas('invoices', [
            'recording_session_id' => $sessionId,
            'client_id' => $this->client->id,
            'total' => 500000,
        ]);

        $invoice = Invoice::where('recording_session_id', $sessionId)->first();
        app(IssueInvoice::class)->execute($invoice);

        // Mock the gateway completely instead of Http::fake since the gateway might throw exceptions early on auth
        PaymentGatewayFactory::fake();

        $payResponse = $this->actingAs($this->client)->postJson("/api/v1/client/invoices/{$invoice->id}/initiate-payment", [
            'gateway' => 'xendit',
        ]);

        $payResponse->assertStatus(201);

        $session->refresh();
        $this->assertNotNull($session->payment_id);
    }
}
