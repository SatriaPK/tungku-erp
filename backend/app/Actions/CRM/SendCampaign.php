<?php

namespace App\Actions\CRM;

use App\Enum\CampaignRecipientStatus;
use App\Enum\CampaignStatus;
use App\Jobs\SendCRMCampaignRecipient as SendRecipientJob;
use App\Models\CRMCampaign;
use App\Models\CRMCampaignRecipient;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SendCampaign
{
    public function handle(CRMCampaign $campaign): void
    {
        DB::transaction(function () use ($campaign): void {
            $lockedCampaign = CRMCampaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();

            $status = $lockedCampaign->status;

            if ($status !== CampaignStatus::Draft && $status !== CampaignStatus::Failed) {
                throw ValidationException::withMessages([
                    'campaign' => 'Kampanye hanya dapat dikirim dari status draft atau failed.',
                ]);
            }

            if ($status === CampaignStatus::Failed) {
                $lockedCampaign->recipients()
                    ->where('status', CampaignRecipientStatus::Failed->value)
                    ->update([
                        'status' => CampaignRecipientStatus::Pending->value,
                        'error' => null,
                        'updated_at' => now(),
                    ]);
            }

            $lockedCampaign->update(['status' => CampaignStatus::Sending]);

            $hasQueuedRecipients = $lockedCampaign->recipients()
                ->where('status', CampaignRecipientStatus::Pending->value)
                ->exists();

            if (! $hasQueuedRecipients) {
                throw ValidationException::withMessages([
                    'campaign' => 'Kampanye tidak memiliki penerima yang dapat dicoba ulang dengan aman.',
                ]);
            }

            $campaignId = $lockedCampaign->id;

            DB::afterCommit(function () use ($campaignId): void {
                $recipientIds = CRMCampaignRecipient::query()
                    ->where('crm_campaign_id', $campaignId)
                    ->where('status', CampaignRecipientStatus::Pending->value)
                    ->pluck('id');

                foreach ($recipientIds as $recipientId) {
                    SendRecipientJob::dispatch($recipientId)->afterCommit();
                }
            });
        });
    }
}
