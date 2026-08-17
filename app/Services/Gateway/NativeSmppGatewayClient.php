<?php

namespace App\Services\Gateway;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class NativeSmppGatewayClient
{
    protected function http(): PendingRequest
    {
        return Http::baseUrl(config('smpp.gateway.url'))
            ->acceptJson()
            ->asJson()
            ->connectTimeout((int) config('smpp.gateway.connect_timeout', 5))
            ->timeout((int) config('smpp.gateway.timeout', 30));
    }

    public function submit(array $message): array
    {
        $response = $this->http()->post('/api/v1/messages', ['message' => $message]);
        if ($response->failed()) {
            throw new RuntimeException($response->json('error') ?: 'Native SMPP gateway rejected the message');
        }
        return $response->json();
    }

    public function health(): array
    {
        return $this->http()->get('/health')->throw()->json();
    }
}
