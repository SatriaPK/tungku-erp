<?php

namespace App\Services\CRM;

use App\Contracts\CRMCampaignEmailProvider;
use App\Exceptions\CRMEmailDeliveryException;
use App\Mail\CRMCampaignMessage;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

class LaravelMailCampaignEmailProvider implements CRMCampaignEmailProvider
{
    public function send(string $email, string $subject, string $message): ?string
    {
        try {
            Mail::to($email)->send(new CRMCampaignMessage($subject, $message));

            return null;
        } catch (TransportExceptionInterface $exception) {
            throw new CRMEmailDeliveryException($exception->getMessage(), false, $exception);
        } catch (Throwable $exception) {
            throw new CRMEmailDeliveryException($exception->getMessage(), true, $exception);
        }
    }
}
