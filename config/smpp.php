<?php

return [
    'currency' => env('SMS_DEFAULT_CURRENCY', 'BDT'),
    'gateway' => [
        'url' => env('SMPP_GATEWAY_URL', 'http://gateway:3001'),
        'events_url' => env('SMPP_GATEWAY_EVENTS_URL', 'http://gateway:3001/live/events'),
        'connect_timeout' => env('SMPP_GATEWAY_CONNECT_TIMEOUT', 5),
        'timeout' => env('SMPP_GATEWAY_TIMEOUT', 30),
        'token' => env('SMPP_GATEWAY_TOKEN'),
        'client_bind_host' => env('SMPP_CLIENT_BIND_HOST', '127.0.0.1'),
        'client_bind_port' => env('SMPP_CLIENT_BIND_PORT', 2775),
    ],
];
