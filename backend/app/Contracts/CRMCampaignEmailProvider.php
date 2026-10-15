<?php

namespace App\Contracts;

interface CRMCampaignEmailProvider
{
    /**
     * @throws \Throwable when delivery did not complete successfully
     */
    public function send(string $email, string $subject, string $message): ?string;
}
