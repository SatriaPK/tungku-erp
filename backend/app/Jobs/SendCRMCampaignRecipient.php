<?php

namespace App\Jobs;

use App\Actions\CRM\SendCRMCampaignRecipient as SendRecipientAction;
use App\Contracts\CRMCampaignEmailProvider;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendCRMCampaignRecipient implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $recipientId)
    {
        $this->onQueue('default');
    }

    public function handle(SendRecipientAction $action, CRMCampaignEmailProvider $provider): void
    {
        $action->handle($this->recipientId, $provider);
    }
}
