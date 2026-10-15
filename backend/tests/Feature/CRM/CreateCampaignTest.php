<?php

use App\Actions\CRM\CreateCampaign;
use App\Actions\CRM\SendCampaign;
use App\Actions\CRM\SendCRMCampaignRecipient as SendRecipientAction;
use App\Contracts\CRMCampaignEmailProvider;
use App\Enum\CampaignRecipientStatus;
use App\Enum\CampaignStatus;
use App\Enum\CRMChannel;
use App\Exceptions\CRMEmailDeliveryException;
use App\Jobs\SendCRMCampaignRecipient;
use App\Mail\CRMCampaignMessage;
use App\Models\CRMCampaign;
use App\Models\CRMEmailWebhookEvent;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Svix\Webhook;

function campaignData(array $overrides = []): array
{
    return array_merge([
        'name' => 'Kampanye pelanggan lama',
        'message' => 'Kami memiliki penawaran baru untuk Anda.',
        'channels' => [CRMChannel::Email->value],
        'client_ids' => [],
    ], $overrides);
}

function campaignEligibleClient(array $attributes = []): User
{
    return User::factory()->client()->create(array_merge([
        'crm_consent_at' => now(),
    ], $attributes));
}

it('creates a draft campaign with unique eligible email recipients', function () {
    $manager = User::factory()->manager()->create();
    $client = campaignEligibleClient();

    $campaign = app(CreateCampaign::class)->handle($manager, campaignData([
        'client_ids' => [$client->id, $client->id],
    ]));

    expect($campaign)->toBeInstanceOf(CRMCampaign::class)
        ->and($campaign->status)->toBe(CampaignStatus::Draft)
        ->and($campaign->channels->all())->toBe([CRMChannel::Email])
        ->and($campaign->recipients)->toHaveCount(1)
        ->and($campaign->recipients->first()->client_id)->toBe($client->id)
        ->and($campaign->recipients->first()->status->value)->toBe('pending');
});

it('rejects non-email channels and empty client lists', function () {
    $manager = User::factory()->manager()->create();
    $client = campaignEligibleClient();

    expect(fn () => app(CreateCampaign::class)->handle($manager, campaignData([
        'channels' => [CRMChannel::Whatsapp->value],
        'client_ids' => [$client->id],
    ])))->toThrow(ValidationException::class);

    expect(fn () => app(CreateCampaign::class)->handle($manager, campaignData()))
        ->toThrow(ValidationException::class);

    expect(CRMCampaign::query()->count())->toBe(0);
});

it('rejects an ineligible client or non-manager creator without creating partial records', function () {
    $manager = User::factory()->manager()->create();
    $withoutConsent = User::factory()->client()->create();
    $operator = User::factory()->operator()->create();

    expect(fn () => app(CreateCampaign::class)->handle($manager, campaignData([
        'client_ids' => [$withoutConsent->id],
    ])))->toThrow(ValidationException::class);

    expect(fn () => app(CreateCampaign::class)->handle($operator, campaignData([
        'client_ids' => [$withoutConsent->id],
    ])))->toThrow(ValidationException::class);

    expect(CRMCampaign::query()->count())->toBe(0)
        ->and(DB::table('crm_campaign_recipients')->count())->toBe(0);
});

it('moves a campaign to sending and queues one job per recipient only after commit', function () {
    Bus::fake();
    $manager = User::factory()->manager()->create();
    $first = campaignEligibleClient();
    $second = campaignEligibleClient();
    $campaign = app(CreateCampaign::class)->handle($manager, campaignData([
        'client_ids' => [$first->id, $second->id],
    ]));

    DB::beginTransaction();
    app(SendCampaign::class)->handle($campaign);
    Bus::assertNothingDispatched();
    DB::commit();

    expect($campaign->fresh()->status)->toBe(CampaignStatus::Sending);
    Bus::assertDispatchedTimes(SendCRMCampaignRecipient::class, 2);
});

it('rechecks consent before sending and records a sanitized failed recipient', function () {
    $manager = User::factory()->manager()->create();
    $client = campaignEligibleClient();
    $campaign = app(CreateCampaign::class)->handle($manager, campaignData(['client_ids' => [$client->id]]));
    app(SendCampaign::class)->handle($campaign);
    $client->update(['crm_consent_at' => null]);
    $provider = new class implements CRMCampaignEmailProvider
    {
        public int $calls = 0;

        public function send(string $email, string $subject, string $message): ?string
        {
            $this->calls++;

            return 'resend-id';
        }
    };

    app(SendRecipientAction::class)->handle($campaign->recipients->first()->id, $provider);

    expect($provider->calls)->toBe(0)
        ->and($campaign->recipients()->first()->status->value)->toBe('failed')
        ->and($campaign->recipients()->first()->error)->not->toContain($client->email)
        ->and($campaign->fresh()->status)->toBe(CampaignStatus::Failed);
});

it('records a successful email and resolves the campaign after every recipient finishes', function () {
    Bus::fake();
    $manager = User::factory()->manager()->create();
    $client = campaignEligibleClient();
    $campaign = app(CreateCampaign::class)->handle($manager, campaignData(['client_ids' => [$client->id]]));
    app(SendCampaign::class)->handle($campaign);
    $provider = new class implements CRMCampaignEmailProvider
    {
        public function send(string $email, string $subject, string $message): ?string
        {
            return 'resend-id';
        }
    };

    app(SendRecipientAction::class)->handle($campaign->recipients->first()->id, $provider);

    expect($campaign->fresh()->status)->toBe(CampaignStatus::Sending)
        ->and($campaign->fresh()->sent_at)->toBeNull()
        ->and($campaign->recipients()->first()->status->value)->toBe('sent');

    app(SendRecipientAction::class)->finalizeWebhookCampaign($campaign->recipients()->first()->id);
    expect($campaign->fresh()->status)->toBe(CampaignStatus::Sending);

    $campaign->recipients()->first()->update(['delivered_at' => now()]);
    app(SendRecipientAction::class)->finalizeWebhookCampaign($campaign->recipients()->first()->id);
    expect($campaign->fresh()->status)->toBe(CampaignStatus::Sent)
        ->and($campaign->fresh()->sent_at)->not->toBeNull();
});

it('retries only definitive delivery failures and never retries an ambiguous transport failure', function () {
    Bus::fake();
    $manager = User::factory()->manager()->create();
    $client = campaignEligibleClient();
    $campaign = app(CreateCampaign::class)->handle($manager, campaignData(['client_ids' => [$client->id]]));
    app(SendCampaign::class)->handle($campaign);
    $recipientId = $campaign->recipients->first()->id;
    $provider = new class implements CRMCampaignEmailProvider
    {
        public int $calls = 0;

        public function send(string $email, string $subject, string $message): ?string
        {

            $this->calls++;

            if ($this->calls === 1) {
                throw new CRMEmailDeliveryException('rejected', true);
            }

            return 'resend-id';
        }
    };

    app(SendRecipientAction::class)->handle($recipientId, $provider);
    expect($campaign->fresh()->status)->toBe(CampaignStatus::Failed)
        ->and($campaign->recipients()->first()->status->value)->toBe('failed');

    app(SendCampaign::class)->handle($campaign->fresh());
    (new SendCRMCampaignRecipient($recipientId))->handle(app(SendRecipientAction::class), $provider);

    expect($provider->calls)->toBe(2)
        ->and($campaign->fresh()->status)->toBe(CampaignStatus::Sending)
        ->and($campaign->recipients()->first()->status->value)->toBe('sent');

    $campaign->recipients()->first()->update(['delivered_at' => now()]);
    app(SendRecipientAction::class)->finalizeWebhookCampaign($recipientId);
    expect($campaign->fresh()->status)->toBe(CampaignStatus::Sent);

    $partialFirstClient = campaignEligibleClient();
    $partialSecondClient = campaignEligibleClient();
    $partialCampaign = app(CreateCampaign::class)->handle($manager, campaignData([
        'client_ids' => [$partialFirstClient->id, $partialSecondClient->id],
    ]));
    app(SendCampaign::class)->handle($partialCampaign);
    $partialProvider = new class implements CRMCampaignEmailProvider
    {
        public int $calls = 0;

        public function send(string $email, string $subject, string $message): ?string
        {
            $this->calls++;

            if ($this->calls === 1) {
                throw new CRMEmailDeliveryException('rejected', true);
            }

            return 'resend-id-'.$this->calls;
        }
    };

    $partialRecipients = $partialCampaign->recipients()->orderBy('id')->get();
    app(SendRecipientAction::class)->handle($partialRecipients[0]->id, $partialProvider);
    expect($partialCampaign->fresh()->status)->toBe(CampaignStatus::Sending);
    app(SendRecipientAction::class)->handle($partialRecipients[1]->id, $partialProvider);
    $partialRecipients[1]->update(['delivered_at' => now()]);
    app(SendRecipientAction::class)->finalizeWebhookCampaign($partialRecipients[1]->id);
    expect($partialCampaign->fresh()->status)->toBe(CampaignStatus::Failed)
        ->and($partialCampaign->recipients()->where('status', 'sent')->count())->toBe(1)
        ->and($partialCampaign->recipients()->where('status', 'failed')->count())->toBe(1);

    $secondClient = campaignEligibleClient();
    $secondCampaign = app(CreateCampaign::class)->handle($manager, campaignData(['client_ids' => [$secondClient->id]]));
    app(SendCampaign::class)->handle($secondCampaign);
    $ambiguous = new class implements CRMCampaignEmailProvider
    {
        public function send(string $email, string $subject, string $message): ?string
        {
            throw new CRMEmailDeliveryException('timeout', false);
        }
    };

    app(SendRecipientAction::class)->handle($secondCampaign->recipients->first()->id, $ambiguous);
    expect($secondCampaign->fresh()->status)->toBe(CampaignStatus::Failed)
        ->and($secondCampaign->recipients()->first()->status->value)->toBe('failed')
        ->and($secondCampaign->recipients()->first()->error)->toContain('tidak diketahui');
});

it('sends the campaign message through the Laravel mail adapter', function () {
    config(['services.resend.key' => 're_test_campaign_key']);
    Mail::fake();

    app(CRMCampaignEmailProvider::class)->send('client@example.test', 'Campaign subject', 'Email campaign content');

    Mail::assertSent(CRMCampaignMessage::class, fn (CRMCampaignMessage $mail): bool => $mail->hasTo('client@example.test'));
});

it('marks a campaign recipient delivered only after a verified Resend delivered webhook', function () {
    Bus::fake();
    $manager = User::factory()->manager()->create();
    $client = campaignEligibleClient();
    $campaign = app(CreateCampaign::class)->handle($manager, campaignData(['client_ids' => [$client->id]]));
    app(SendCampaign::class)->handle($campaign);
    $recipient = $campaign->recipients()->first();
    $provider = new class implements CRMCampaignEmailProvider
    {
        public function send(string $email, string $subject, string $message): ?string
        {
            return 'resend-message-123';
        }
    };

    app(SendRecipientAction::class)->handle($recipient->id, $provider);
    expect($recipient->fresh()->status)->toBe(CampaignRecipientStatus::Sent)
        ->and($campaign->fresh()->status)->toBe(CampaignStatus::Sending);

    $id = 'evt_delivery_1';
    $secret = 'whsec_'.base64_encode('webhook-test-secret');
    config(['services.resend.webhook_secret' => $secret]);
    $timestamp = (string) time();
    $payload = '{"type":"email.delivered","created_at":"2026-10-12T10:18:33+00:00","data":{"email_id":"resend-message-123"}}';
    $signature = (new Webhook($secret))->sign($id, $timestamp, $payload);
    $webhookResponse = $this->call('POST', '/api/v1/webhooks/resend', [], [], [], [
        'HTTP_SVIX_ID' => $id,
        'HTTP_SVIX_TIMESTAMP' => $timestamp,
        'HTTP_SVIX_SIGNATURE' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $payload);
    $webhookResponse->assertOk();
    expect($recipient->fresh()->status)->toBe(CampaignRecipientStatus::Sent)
        ->and($recipient->fresh()->delivered_at)->not->toBeNull()
        ->and($campaign->fresh()->status)->toBe(CampaignStatus::Sent);

    $this->call('POST', '/api/v1/webhooks/resend', [], [], [], [
        'HTTP_SVIX_ID' => $id,
        'HTTP_SVIX_TIMESTAMP' => $timestamp,
        'HTTP_SVIX_SIGNATURE' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $payload)->assertOk();
    expect(CRMEmailWebhookEvent::count())->toBe(1);
});

it('ignores out-of-order Resend delivery events', function () {
    Bus::fake();
    $manager = User::factory()->manager()->create();
    $client = campaignEligibleClient();
    $campaign = app(CreateCampaign::class)->handle($manager, campaignData(['client_ids' => [$client->id]]));
    app(SendCampaign::class)->handle($campaign);
    $recipient = $campaign->recipients()->first();
    $provider = new class implements CRMCampaignEmailProvider
    {
        public function send(string $email, string $subject, string $message): ?string
        {
            return 'resend-message-out-of-order';
        }
    };
    app(SendRecipientAction::class)->handle($recipient->id, $provider);

    $secret = 'whsec_'.base64_encode('webhook-test-secret');
    config(['services.resend.webhook_secret' => $secret]);
    $sendWebhook = function (string $id, string $type, string $occurredAt) use ($secret): void {
        $timestamp = (string) time();
        $payload = json_encode([
            'type' => $type,
            'created_at' => $occurredAt,
            'data' => ['email_id' => 'resend-message-out-of-order'],
        ], JSON_THROW_ON_ERROR);
        $signature = (new Webhook($secret))->sign($id, $timestamp, $payload);

        test()->call('POST', '/api/v1/webhooks/resend', [], [], [], [
            'HTTP_SVIX_ID' => $id,
            'HTTP_SVIX_TIMESTAMP' => $timestamp,
            'HTTP_SVIX_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertOk();
    };

    $sendWebhook('evt_newer', 'email.bounced', now()->addMinute()->toISOString());
    $sendWebhook('evt_older', 'email.delivered', now()->subMinute()->toISOString());

    expect($recipient->fresh()->status)->toBe(CampaignRecipientStatus::Failed)
        ->and(CRMEmailWebhookEvent::query()->where('processing_status', 'processed')->count())->toBe(1)
        ->and(CRMEmailWebhookEvent::query()->where('processing_status', 'ignored')->count())->toBe(1);
});

it('rejects Resend webhooks with an invalid signature', function () {
    config(['services.resend.webhook_secret' => 'whsec_'.base64_encode('webhook-test-secret')]);
    $payload = json_encode(['type' => 'email.delivered', 'data' => ['email_id' => 'unknown']], JSON_THROW_ON_ERROR);
    $response = $this->call('POST', '/api/v1/webhooks/resend', [], [], [], [
        'HTTP_SVIX_ID' => 'evt_invalid',
        'HTTP_SVIX_TIMESTAMP' => (string) time(),
        'HTTP_SVIX_SIGNATURE' => 'v1,invalid',
        'CONTENT_TYPE' => 'application/json',
    ], $payload);

    $response->assertUnauthorized();
});
