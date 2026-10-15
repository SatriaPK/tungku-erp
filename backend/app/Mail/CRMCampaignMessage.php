<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class CRMCampaignMessage extends Mailable
{
    public function __construct(public string $subjectLine, public string $messageBody) {}

    public function build(): self
    {
        return $this->subject($this->subjectLine)->text('mail.crm-campaign');
    }
}
