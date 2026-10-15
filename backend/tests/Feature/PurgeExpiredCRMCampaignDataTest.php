<?php

use App\Enum\CampaignRecipientStatus;
use App\Enum\CampaignStatus;
use App\Enum\CRMChannel;
use App\Models\CRMCampaign;
use App\Models\CRMCampaignRecipient;
use App\Models\CRMEmailWebhookEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function retentionCampaign(User $manager, array $attributes = []): CRMCampaign
{
    return CRMCampaign::query()->create(array_merge([
        'created_by' => $manager->id,
        'name' => 'Retention campaign',
        'message' => 'Promotional email',
        'channels' => [CRMChannel::Email],
        'status' => CampaignStatus::Draft,
    ], $attributes));
}

it('deletes campaigns and associated recipient data after the retention period', function () {
    $manager = User::factory()->manager()->create();
    $client = User::factory()->client()->create();
    $campaign = retentionCampaign($manager, ['created_at' => now()->subDays(91)]);
    DB::table('crm_campaigns')->where('id', $campaign->id)->update(['created_at' => now()->subDays(91)]);
    $recipient = CRMCampaignRecipient::query()->create([
        'crm_campaign_id' => $campaign->id,
        'client_id' => $client->id,
        'channel' => CRMChannel::Email,
        'status' => CampaignRecipientStatus::Failed,
        'error' => 'Provider email melaporkan kegagalan delivery.',
    ]);
    $event = CRMEmailWebhookEvent::query()->create([
        'event_id' => 'evt_old',
        'event_type' => 'email.failed',
        'provider_message_id' => 'msg_old',
        'processing_status' => 'processed',
        'created_at' => now()->subDays(91),
    ]);
    DB::table('crm_email_webhook_events')->where('id', $event->id)->update(['created_at' => now()->subDays(91)]);

    $this->artisan('crm:purge-expired-data')->assertSuccessful();

    $this->assertDatabaseMissing('crm_campaigns', ['id' => $campaign->id]);
    $this->assertDatabaseMissing('crm_campaign_recipients', ['id' => $recipient->id]);
    $this->assertDatabaseMissing('crm_email_webhook_events', ['id' => $event->id]);
});

it('preserves data within retention and supports dry run', function () {
    $manager = User::factory()->manager()->create();
    $campaign = retentionCampaign($manager, ['created_at' => now()->subDays(89)]);
    DB::table('crm_campaigns')->where('id', $campaign->id)->update(['created_at' => now()->subDays(89)]);
    $event = CRMEmailWebhookEvent::query()->create([
        'event_id' => 'evt_recent',
        'event_type' => 'email.delivered',
        'provider_message_id' => 'msg_recent',
        'processing_status' => 'processed',
        'created_at' => now()->subDays(89),
    ]);
    DB::table('crm_email_webhook_events')->where('id', $event->id)->update(['created_at' => now()->subDays(89)]);

    $this->artisan('crm:purge-expired-data --dry-run')
        ->expectsOutput('Eligible campaigns: 0; webhook events: 0.')
        ->assertSuccessful();

    $this->assertDatabaseHas('crm_campaigns', ['id' => $campaign->id]);
    $this->assertDatabaseHas('crm_email_webhook_events', ['id' => $event->id]);
});

it('deletes only the configured batch of expired campaigns', function () {
    $manager = User::factory()->manager()->create();
    $expiredCampaigns = collect(range(1, 3))->map(function () use ($manager) {
        $campaign = retentionCampaign($manager);
        DB::table('crm_campaigns')->where('id', $campaign->id)->update(['created_at' => now()->subDays(91)]);

        return $campaign;
    });

    $this->artisan('crm:purge-expired-data --limit=2')->assertSuccessful();

    expect(CRMCampaign::query()->whereIn('id', $expiredCampaigns->pluck('id'))->count())->toBe(1);
});
