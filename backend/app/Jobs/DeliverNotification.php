<?php

namespace App\Jobs;

use App\Actions\Notification\DeliverNotification as DeliverNotificationAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeliverNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public int $deliveryId)
    {
        $this->onQueue('notifications');
    }

    public function handle(DeliverNotificationAction $action): void
    {
        $action->handle($this->deliveryId);
    }
}
