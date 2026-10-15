<?php

namespace App\Actions\CRM;

use App\Contracts\CRMCampaignEmailProvider;
use App\Enum\CampaignRecipientStatus;
use App\Enum\CampaignStatus;
use App\Enum\CRMChannel;
use App\Enum\Role;
use App\Exceptions\CRMEmailDeliveryException;
use App\Models\CRMCampaign;
use App\Models\CRMCampaignRecipient;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

class SendCRMCampaignRecipient
{
    public function handle(int $recipientId, CRMCampaignEmailProvider $provider): void
    {
        $recipient = CRMCampaignRecipient::query()->with(['campaign', 'client'])->find($recipientId);
        if ($recipient !== null) {
            /** @var CRMCampaign $campaign */
            $campaign = $recipient->getRelation('campaign');
            /** @var User $client */
            $client = $recipient->getRelation('client');
        }

        if ($recipient === null || $recipient->status !== CampaignRecipientStatus::Pending || $campaign->status !== CampaignStatus::Sending) {
            return;
        }
        if ($recipient->channel !== CRMChannel::Email || $client->role !== Role::Client || ! $client->is_active || $client->crm_consent_at === null || filter_var($client->email, FILTER_VALIDATE_EMAIL) === false) {
            $recipient->update(['status' => CampaignRecipientStatus::Failed, 'error' => 'Penerima tidak lagi memenuhi syarat pengiriman email CRM.']);
            $this->finalizeCampaign($recipientId);

            return;
        }
        try {
            $recipient = DB::transaction(function () use ($recipientId): ?CRMCampaignRecipient {
                $locked = CRMCampaignRecipient::query()->whereKey($recipientId)->lockForUpdate()->first();
                if ($locked === null || $locked->status !== CampaignRecipientStatus::Pending) {
                    return null;
                }
                $locked->load(['campaign', 'client']);
                /** @var CRMCampaign $lockedCampaign */
                $lockedCampaign = $locked->getRelation('campaign');
                /** @var User $lockedClient */
                $lockedClient = $locked->getRelation('client');
                if ($lockedCampaign->status !== CampaignStatus::Sending || $lockedClient->role !== Role::Client || ! $lockedClient->is_active || $lockedClient->crm_consent_at === null || filter_var($lockedClient->email, FILTER_VALIDATE_EMAIL) === false) {
                    $locked->update(['status' => CampaignRecipientStatus::Failed, 'error' => 'Penerima tidak lagi memenuhi syarat pengiriman email CRM.']);

                    return null;
                }

                return $locked;
            });
            if ($recipient === null) {
                $this->finalizeCampaign($recipientId);

                return;
            }
            $messageId = $provider->send($client->email, $campaign->name, $campaign->message);
            CRMCampaignRecipient::query()->whereKey($recipientId)->where('status', CampaignRecipientStatus::Pending->value)->update([
                'status' => CampaignRecipientStatus::Sent->value,
                'sent_at' => now(),
                'provider_message_id' => $messageId,
                'delivered_at' => null,
                'error' => null,
                'updated_at' => now(),
            ]);
        } catch (CRMEmailDeliveryException $exception) {
            if ($exception->deliveryIsDefinitive) {
                $this->markFailed($recipientId);
            } else {
                $this->markAmbiguous($recipientId);
            }
            $this->finalizeCampaign($recipientId);

            return;
        } catch (Throwable $exception) {
            $this->markFailed($recipientId);
            $this->finalizeCampaign($recipientId);

            return;
        }
        $this->finalizeCampaign($recipientId);
    }

    private function markFailed(int $recipientId): void
    {
        CRMCampaignRecipient::query()->whereKey($recipientId)
            ->where('status', CampaignRecipientStatus::Pending->value)
            ->update([
                'status' => CampaignRecipientStatus::Failed->value,
                'error' => 'Pengiriman email gagal.',
                'updated_at' => now(),
            ]);
    }

    private function markAmbiguous(int $recipientId): void
    {
        CRMCampaignRecipient::query()->whereKey($recipientId)
            ->where('status', CampaignRecipientStatus::Pending->value)
            ->update([
                'status' => CampaignRecipientStatus::Failed->value,
                'error' => 'Hasil pengiriman email tidak diketahui; tidak akan dicoba ulang otomatis.',
                'updated_at' => now(),
            ]);
    }

    private function finalizeCampaign(int $recipientId): void
    {
        $recipient = CRMCampaignRecipient::query()->with('campaign')->find($recipientId);

        if ($recipient === null) {
            return;
        }

        DB::transaction(function () use ($recipientId): void {
            $freshRecipient = CRMCampaignRecipient::query()->find($recipientId);

            if ($freshRecipient === null) {
                return;
            }

            $recipient = $freshRecipient;
            /** @var CRMCampaign|null $campaign */
            $campaign = $recipient->campaign()->lockForUpdate()->first();

            if ($campaign === null || $campaign->status !== CampaignStatus::Sending) {
                return;
            }

            $hasPendingRecipients = $campaign->recipients()
                ->where('status', CampaignRecipientStatus::Pending->value)
                ->exists();

            $hasUndeliveredRecipients = $campaign->recipients()
                ->where('status', CampaignRecipientStatus::Sent->value)
                ->whereNull('delivered_at')
                ->exists();

            if ($hasPendingRecipients || $hasUndeliveredRecipients) {
                return;
            }

            $hasFailedRecipients = $campaign->recipients()
                ->where('status', CampaignRecipientStatus::Failed->value)
                ->exists();

            $nextStatus = $hasFailedRecipients ? CampaignStatus::Failed : CampaignStatus::Sent;

            if (! $campaign->status->canTransitionTo($nextStatus)) {
                return;
            }

            $campaign->update([
                'status' => $nextStatus,
                'sent_at' => $hasFailedRecipients ? null : now(),
            ]);
        });
    }

    public function finalizeWebhookCampaign(int $recipientId): void
    {
        $recipient = CRMCampaignRecipient::query()->find($recipientId);

        if ($recipient === null) {
            return;
        }

        DB::transaction(function () use ($recipientId): void {
            $recipient = CRMCampaignRecipient::query()->find($recipientId);

            if ($recipient === null) {
                return;
            }

            /** @var CRMCampaign|null $campaign */
            $campaign = $recipient->campaign()->lockForUpdate()->first();

            if ($campaign === null || $campaign->status !== CampaignStatus::Sending) {
                return;
            }

            $hasOutstandingDelivery = $campaign->recipients()
                ->where(function ($query): void {
                    $query->where('status', CampaignRecipientStatus::Pending->value)
                        ->orWhere(function ($query): void {
                            $query->where('status', CampaignRecipientStatus::Sent->value)
                                ->whereNull('delivered_at');
                        });
                })
                ->exists();

            if ($hasOutstandingDelivery) {
                return;
            }

            $hasFailedRecipients = $campaign->recipients()
                ->where('status', CampaignRecipientStatus::Failed->value)
                ->exists();
            $nextStatus = $hasFailedRecipients ? CampaignStatus::Failed : CampaignStatus::Sent;

            if ($campaign->status->canTransitionTo($nextStatus)) {
                $campaign->update([
                    'status' => $nextStatus,
                    'sent_at' => $hasFailedRecipients ? null : now(),
                ]);
            }
        });
    }
}
