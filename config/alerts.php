<?php

return [
    'balance_threshold' => (float) env('BALANCE_ALERT_THRESHOLD', 1.00),
    'email' => env('BALANCE_ALERT_EMAIL'),
    'telegram_bot_token' => env('TELEGRAM_BOT_TOKEN'),
    'telegram_chat_id' => env('TELEGRAM_CHAT_ID'),
];
