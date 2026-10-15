<?php

use App\Actions\CRM\CreateCampaign;
use App\Actions\CRM\SendCampaign;
use App\Actions\CRM\SendCRMCampaignRecipient;
use App\Contracts\CRMCampaignEmailProvider;
use App\Enum\CampaignRecipientStatus;
use App\Enum\CRMChannel;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Laravel\Sanctum\Sanctum;

it('allows an authenticated client to revoke crm consent immediately', function () {
    $client = User::factory()->client()->create(['crm_consent_at' => now()]);
    Sanctum::actingAs($client);

    $this->deleteJson('/api/v1/auth/crm-consent')->assertNoContent();

    expect($client->fresh()->crm_consent_at)->toBeNull();
});

it('does not allow guests to revoke crm consent', function () {
    $this->deleteJson('/api/v1/auth/crm-consent')->assertUnauthorized();
});

it('does not allow an inactive client to use the consent endpoint', function () {
    Sanctum::actingAs(User::factory()->client()->inactive()->create(['crm_consent_at' => now()]));

    $this->deleteJson('/api/v1/auth/crm-consent')->assertForbidden();
});

it('stops queued delivery when consent is revoked before the recipient job runs', function () {
    $manager = User::factory()->manager()->create();
    $client = User::factory()->client()->create(['crm_consent_at' => now()]);
    $campaign = app(CreateCampaign::class)->handle($manager, [
        'name' => 'Consent revoke check',
        'message' => 'Email promotion',
        'channels' => [CRMChannel::Email->value],
        'client_ids' => [$client->id],
    ]);

    Bus::fake();
    app(SendCampaign::class)->handle($campaign);
    Sanctum::actingAs($client);
    $this->deleteJson('/api/v1/auth/crm-consent')->assertNoContent();

    $provider = new class implements CRMCampaignEmailProvider
    {
        public int $sendCount = 0;

        public function send(string $email, string $subject, string $message): ?string
        {
            $this->sendCount++;

            return 'must-not-send';
        }
    };
    $recipient = $campaign->recipients()->firstOrFail();
    app(SendCRMCampaignRecipient::class)->handle($recipient->id, $provider);

    expect($provider->sendCount)->toBe(0)
        ->and($recipient->fresh()->status)->toBe(CampaignRecipientStatus::Failed);
});
