<?php

namespace App\Notifications;

use App\Notifications\Channels\TelegramChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowBalanceNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly float $balance,
        public readonly float $threshold,
        public readonly string $currency,
        public readonly ?int $smsId = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        $channels = [];
        if (config('alerts.email')) $channels[] = 'mail';
        if (config('alerts.telegram_bot_token') && config('alerts.telegram_chat_id')) $channels[] = TelegramChannel::class;
        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('SMPP low-balance alert')
            ->greeting('SMPP balance alert')
            ->line("Customer: {$notifiable->name}")
            ->line('Current balance: ' . number_format($this->balance, 6) . ' ' . $this->currency)
            ->line('Minimum threshold: ' . number_format($this->threshold, 6) . ' ' . $this->currency)
            ->line('SMPP traffic has been blocked automatically.');
    }

    public function toTelegram(object $notifiable): array
    {
        return [
            'text' => "SMPP balance alert\nCustomer: {$notifiable->name}\nBalance: " . number_format($this->balance, 6) . " {$this->currency}\nThreshold: " . number_format($this->threshold, 6) . " {$this->currency}\nTraffic: BLOCKED",
        ];
    }
}
