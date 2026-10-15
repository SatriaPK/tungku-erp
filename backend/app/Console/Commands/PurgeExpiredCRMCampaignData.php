<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('crm:purge-expired-data {--days=90 : Retention period in days} {--limit=500 : Maximum campaign records to delete} {--dry-run : Show eligible records without deleting them}')]
#[Description('Delete CRM campaign data older than the configured retention period')]
class PurgeExpiredCRMCampaignData extends Command
{
    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $limit = max(1, (int) $this->option('limit'));
        $cutoff = now()->subDays($days);
        $campaignIds = DB::table('crm_campaigns')
            ->where('created_at', '<=', $cutoff)
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        $webhookEventIds = DB::table('crm_email_webhook_events')
            ->where('created_at', '<=', $cutoff)
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        if ($this->option('dry-run')) {
            $this->info("Eligible campaigns: {$campaignIds->count()}; webhook events: {$webhookEventIds->count()}.");

            return self::SUCCESS;
        }

        DB::transaction(function () use ($campaignIds, $webhookEventIds, $cutoff): void {
            if ($campaignIds->isNotEmpty()) {
                DB::table('crm_campaigns')
                    ->whereIn('id', $campaignIds)
                    ->where('created_at', '<=', $cutoff)
                    ->delete();
            }

            if ($webhookEventIds->isNotEmpty()) {
                DB::table('crm_email_webhook_events')->whereIn('id', $webhookEventIds)->delete();
            }

        });

        $this->info("Deleted {$campaignIds->count()} campaigns and {$webhookEventIds->count()} webhook events.");

        return self::SUCCESS;
    }
}
