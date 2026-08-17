<?php

namespace App\Services\Jasmin;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class JasminHttpAdapter
{
    public function client(): PendingRequest
    {
        return Http::asForm()
            ->accept('text/plain')
            ->connectTimeout((int) config('smpp.jasmin.connect_timeout', 5))
            ->timeout((int) config('smpp.jasmin.timeout', 20));
    }

    public function send(array $message): string
    {
        $payload = [
            'username' => config('smpp.jasmin.username'),
            'password' => config('smpp.jasmin.password'),
            'to' => $message['destination'],
            'from' => $message['source'],
            'content' => $message['content'],
            'coding' => $message['coding'] ?? 0,
            'priority' => $message['priority'] ?? 0,
            'dlr' => 'yes',
            'dlr-url' => config('smpp.jasmin.dlr_url'),
            'dlr-level' => $message['dlr_level'] ?? 2,
            'dlr-method' => 'POST',
        ];

        $response = $this->client()->post((string) config('smpp.jasmin.http_url'), $payload);

        if ($response->successful()) {
            $jasminId = trim((string) $response->body());
            if ($jasminId === '') {
                throw new RuntimeException('Jasmin returned an empty message id.');
            }
            return $jasminId;
        }

        $detail = trim((string) $response->body()) ?: 'Unknown Jasmin error';
        throw new RuntimeException(sprintf('Jasmin HTTP %s: %s', $response->status(), $detail));
    }
}
