<?php

namespace App\Listeners;

use App\Events\CustomerBalanceBelowThreshold;
use App\Notifications\BalanceAlertRecipient;
use App\Notifications\LowBalanceNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SendLowBalanceAlert implements ShouldQueue
{
    public int $tries = 5;
    public array $backoff = [10, 60, 300, 900];

    public function handle(CustomerBalanceBelowThreshold $event): void
    {
        if (!config('alerts.email') && !(config('alerts.telegram_bot_token') && config('alerts.telegram_chat_id'))) return;

        Notification::send(
            new BalanceAlertRecipient(),
            new LowBalanceNotification($event->balance, $event->threshold, $event->currency, $event->smsId)
        );
    }
}
