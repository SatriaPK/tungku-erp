<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CRM\SendCRMCampaignRecipient;
use App\Enum\CampaignRecipientStatus;
use App\Http\Controllers\Controller;
use App\Models\CRMCampaignRecipient;
use App\Models\CRMEmailWebhookEvent;
use App\Services\CRM\ResendWebhookSignatureVerifier;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResendWebhookController extends Controller
{
    public function __invoke(Request $request, ResendWebhookSignatureVerifier $verifier): JsonResponse
    {
        $request->attributes->set('resend_raw_body', $request->getContent());

        if (! $verifier->verify($request)) {
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $payload = $request->json()->all();
        $eventId = $request->header('svix-id');
        $type = $payload['type'] ?? null;
        $messageId = $payload['data']['email_id'] ?? null;
        if (! is_string($eventId) || ! is_string($type) || ! is_string($messageId)) {
            return response()->json(['message' => 'Invalid event payload'], 400);
        }

        try {
            DB::transaction(function () use ($eventId, $type, $messageId, $payload): void {
                $event = CRMEmailWebhookEvent::query()->create([
                    'event_id' => $eventId,
                    'event_type' => $type,
                    'provider_message_id' => $messageId,
                    'occurred_at' => $payload['created_at'] ?? null,
                    'processing_status' => 'received',
                ]);

                $recipient = CRMCampaignRecipient::query()->where('provider_message_id', $messageId)->lockForUpdate()->first();
                if ($recipient === null) {
                    $event->update(['processing_status' => 'unmatched']);

                    return;
                }

                if (! in_array($recipient->status, [CampaignRecipientStatus::Pending, CampaignRecipientStatus::Sent], true)) {
                    $event->update(['processing_status' => 'ignored']);

                    return;
                }

                $occurredAt = $payload['created_at'] ?? now()->toISOString();
                $occurredAt = now()->parse($occurredAt);
                $lastDeliveryEventAt = $recipient->last_delivery_event_at;
                $latestEventAt = $lastDeliveryEventAt === null || $occurredAt->greaterThan($lastDeliveryEventAt)
                    ? $occurredAt
                    : $lastDeliveryEventAt;
                if ($lastDeliveryEventAt !== null && $lastDeliveryEventAt->greaterThanOrEqualTo($occurredAt)) {
                    $event->update(['processing_status' => 'stale']);

                    return;
                }

                if ($type === 'email.delivered') {
                    $recipient->update([
                        'status' => CampaignRecipientStatus::Sent,
                        'delivered_at' => $occurredAt,
                        'last_delivery_event_at' => $latestEventAt,
                        'error' => null,
                    ]);
                } elseif (in_array($type, ['email.bounced', 'email.failed'], true)) {
                    $recipient->update([
                        'status' => CampaignRecipientStatus::Failed,
                        'last_delivery_event_at' => $latestEventAt,
                        'error' => 'Provider email melaporkan kegagalan delivery.',
                    ]);
                } else {
                    $recipient->update(['last_delivery_event_at' => $occurredAt]);
                }

                $event->update(['processing_status' => 'processed']);
            });
        } catch (UniqueConstraintViolationException) {
            return response()->json(['status' => 'success', 'message' => 'Duplicate event'], 200);
        }

        if (in_array($type, ['email.delivered', 'email.bounced', 'email.failed'], true)) {
            $recipient = CRMCampaignRecipient::query()->where('provider_message_id', $messageId)->first();
            if ($recipient !== null) {
                app(SendCRMCampaignRecipient::class)->finalizeWebhookCampaign($recipient->id);
            }
        }

        return response()->json(['status' => 'success'], 200);
    }
}
