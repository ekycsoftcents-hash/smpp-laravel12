<?php

namespace App\Notifications;

use Illuminate\Notifications\RoutesNotifications;

class BalanceAlertRecipient
{
    use RoutesNotifications;

    public function __construct(public readonly string $name = 'SMPP Administrator')
    {
    }

    public function routeNotificationForMail(): ?string
    {
        return config('alerts.email');
    }
}
