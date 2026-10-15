<?php

use App\Actions\CRM\CreateCampaign;
use App\Enum\CampaignRecipientStatus;
use App\Enum\CampaignStatus;
use App\Enum\CRMChannel;
use App\Jobs\SendCRMCampaignRecipient;
use App\Models\CRMCampaign;
use App\Models\CRMCampaignRecipient;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

function crmApiEligibleClient(array $attributes = []): User
{
    return User::factory()->client()->create(array_merge([
        'crm_consent_at' => now(),
    ], $attributes));
}

function crmApiCampaignData(array $overrides = []): array
{
    return array_merge([
        'name' => 'Penawaran akhir tahun',
        'message' => 'Informasi promo untuk pelanggan.',
        'channels' => [CRMChannel::Email->value],
        'client_ids' => [],
    ], $overrides);
}

beforeEach(function () {
    Sanctum::actingAs(User::factory()->manager()->create());
});

it('creates a campaign from validated client ids and queues dispatch asynchronously', function () {
    Bus::fake();
    $client = crmApiEligibleClient();

    $response = $this->postJson('/api/v1/manager/crm/campaigns', crmApiCampaignData([
        'client_ids' => [$client->id],
        'status' => 'sent',
        'created_by' => 99999,
        'recipients' => [['client_id' => 99999]],
    ]));

    $response->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.recipient_counts.total', 1)
        ->assertJsonMissingPath('data.recipients.0.client_email');

    $campaign = CRMCampaign::query()->firstOrFail();
    expect($campaign->created_by)->toBe(auth()->id())
        ->and($campaign->recipients()->count())->toBe(1);
    Bus::assertNothingDispatched();

    $this->postJson("/api/v1/manager/crm/campaigns/{$campaign->id}/dispatch")
        ->assertAccepted()
        ->assertJsonPath('data.status', 'sending');
});

it('does not queue delivery until the surrounding transaction commits', function () {
    Bus::fake();
    $client = crmApiEligibleClient();
    $campaign = app(CreateCampaign::class)->handle(
        auth()->user(),
        crmApiCampaignData(['client_ids' => [$client->id]]),
    );

    DB::beginTransaction();

    try {
        $this->postJson("/api/v1/manager/crm/campaigns/{$campaign->id}/dispatch")
            ->assertAccepted();
        Bus::assertNothingDispatched();

        DB::commit();
    } catch (Throwable $exception) {
        DB::rollBack();
        throw $exception;
    }

    Bus::assertDispatchedTimes(SendCRMCampaignRecipient::class, 1);
});

it('dispatches only failed recipients when a failed campaign is explicitly resubmitted', function () {
    Bus::fake();
    $firstClient = crmApiEligibleClient();
    $secondClient = crmApiEligibleClient();
    $campaign = app(CreateCampaign::class)->handle(
        auth()->user(),
        crmApiCampaignData(['client_ids' => [$firstClient->id, $secondClient->id]]),
    );
    $recipients = $campaign->recipients()->orderBy('id')->get();
    $recipients[0]->update(['status' => CampaignRecipientStatus::Failed]);
    $recipients[1]->update(['status' => CampaignRecipientStatus::Sent]);
    $campaign->update(['status' => CampaignStatus::Failed]);

    $this->postJson("/api/v1/manager/crm/campaigns/{$campaign->id}/dispatch")
        ->assertAccepted();

    Bus::assertDispatchedTimes(SendCRMCampaignRecipient::class, 1);
    Bus::assertDispatched(SendCRMCampaignRecipient::class, fn (SendCRMCampaignRecipient $job): bool => $job->recipientId === $recipients[0]->id);
    Bus::assertNotDispatched(SendCRMCampaignRecipient::class, fn (SendCRMCampaignRecipient $job): bool => $job->recipientId === $recipients[1]->id);
    expect($recipients[0]->fresh()->status)->toBe(CampaignRecipientStatus::Pending)
        ->and($recipients[1]->fresh()->status)->toBe(CampaignRecipientStatus::Sent);
});

it('does not expose recipient details in campaign lists', function () {
    $client = crmApiEligibleClient();
    $campaign = app(CreateCampaign::class)->handle(
        auth()->user(),
        crmApiCampaignData(['client_ids' => [$client->id]]),
    );
    $campaign->recipients()->first()->update(['error' => 'Private delivery detail']);

    $this->getJson('/api/v1/manager/crm/campaigns')
        ->assertOk()
        ->assertJsonMissing(['client_name' => $client->name])
        ->assertJsonMissing(['error' => 'Private delivery detail'])
        ->assertJsonMissing(['client_email' => $client->email]);
});

it('rejects invalid payloads and ineligible recipients without creating records', function () {
    $client = User::factory()->client()->create(['crm_consent_at' => null]);

    $this->postJson('/api/v1/manager/crm/campaigns', crmApiCampaignData([
        'channels' => ['whatsapp'],
        'client_ids' => [$client->id],
    ]))->assertUnprocessable()->assertJsonValidationErrors(['channels.0']);

    expect(CRMCampaign::query()->count())->toBe(0)
        ->and(CRMCampaignRecipient::query()->count())->toBe(0);

    $this->postJson('/api/v1/manager/crm/campaigns', crmApiCampaignData([
        'client_ids' => [$client->id],
    ]))->assertUnprocessable();

    expect(CRMCampaign::query()->count())->toBe(0)
        ->and(CRMCampaignRecipient::query()->count())->toBe(0);
});

it('lists campaigns with pagination and reads recipient outcomes without provider ids or email addresses', function () {
    $client = crmApiEligibleClient();
    $campaign = app(CreateCampaign::class)->handle(
        auth()->user(),
        crmApiCampaignData(['client_ids' => [$client->id]]),
    );
    $campaign->recipients()->first()->update([
        'provider_message_id' => 'provider-private-id',
        'error' => 'Pengiriman gagal.',
    ]);

    $this->getJson('/api/v1/manager/crm/campaigns?per_page=1')
        ->assertOk()
        ->assertJsonPath('data.0.id', $campaign->id)
        ->assertJsonPath('data.0.recipient_counts.total', 1)
        ->assertJsonMissing(['provider-private-id', $client->email]);

    $this->getJson("/api/v1/manager/crm/campaigns/{$campaign->id}")
        ->assertOk()
        ->assertJsonPath('data.recipients.0.client_name', $client->name)
        ->assertJsonPath('data.recipients.0.error', 'Pengiriman gagal.')
        ->assertJsonMissing(['provider-private-id', $client->email]);
});

it('restricts campaign endpoints to authenticated active managers', function () {
    Auth::forgetGuards();
    $this->getJson('/api/v1/manager/crm/campaigns')->assertUnauthorized();

    Sanctum::actingAs(User::factory()->operator()->create());
    $this->getJson('/api/v1/manager/crm/campaigns')->assertForbidden();

    Sanctum::actingAs(User::factory()->manager()->inactive()->create());
    $this->getJson('/api/v1/manager/crm/campaigns')->assertForbidden();
});

it('prevents a duplicate dispatch of a campaign already sending', function () {
    $client = crmApiEligibleClient();
    $campaign = app(CreateCampaign::class)->handle(
        auth()->user(),
        crmApiCampaignData(['client_ids' => [$client->id]]),
    );
    $campaign->update(['status' => CampaignStatus::Sending]);

    $this->postJson("/api/v1/manager/crm/campaigns/{$campaign->id}/dispatch")
        ->assertUnprocessable();

    expect($campaign->recipients()->first()->status->value)->toBe('pending');
});
