<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TelegramChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        $message = $notification->toTelegram($notifiable);
        $token = config('alerts.telegram_bot_token');
        $chatId = config('alerts.telegram_chat_id');
        if (!$token || !$chatId) return;

        $response = Http::timeout(10)->retry(3, 250)->post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $message['text'] ?? '',
            'disable_web_page_preview' => true,
        ]);
        if ($response->failed()) throw new RuntimeException('Telegram balance alert failed: ' . $response->body());
    }
}
