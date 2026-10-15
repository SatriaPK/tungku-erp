<?php

namespace App\Services\CRM;

use Illuminate\Http\Request;
use Svix\Exception\WebhookVerificationException;
use Svix\Webhook;

class ResendWebhookSignatureVerifier
{
    public function verify(Request $request): bool
    {
        $secret = config('services.resend.webhook_secret');
        if (! is_string($secret) || $secret === '') {
            return false;
        }

        $rawBody = $request->getContent();

        try {
            $headers = [];
            foreach (['svix-id', 'svix-timestamp', 'svix-signature'] as $header) {
                $value = $request->header($header);
                if (is_string($value)) {
                    $headers[$header] = $value;
                }
            }

            (new Webhook($secret))->verify($rawBody, $headers);

            return true;
        } catch (WebhookVerificationException) {
            return false;
        }
    }
}
