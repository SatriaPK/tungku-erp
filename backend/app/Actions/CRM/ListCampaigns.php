<?php

namespace App\Actions\CRM;

use App\Models\CRMCampaign;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListCampaigns
{
    public function handle(int $perPage): LengthAwarePaginator
    {
        return CRMCampaign::query()
            ->withCount([
                'recipients',
                'recipients as pending_recipients_count' => fn ($query) => $query->where('status', 'pending'),
                'recipients as sent_recipients_count' => fn ($query) => $query->where('status', 'sent'),
                'recipients as failed_recipients_count' => fn ($query) => $query->where('status', 'failed'),
            ])
            ->latest('id')
            ->paginate($perPage);
    }
}
