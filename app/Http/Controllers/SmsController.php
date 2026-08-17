<?php

namespace App\Http\Controllers;

use App\Jobs\SendSmsToGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SmsController extends Controller
{
    public function send(Request $request)
    {
        $data = $request->validate([
            'from' => ['required', 'string', 'max:40'],
            'to' => ['required', 'string', 'max:40'],
            'content' => ['required', 'string', 'max:2000'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
            'customer_id' => ['nullable', 'integer', 'exists:users,id'],
            'provider_id' => ['nullable', 'integer', 'exists:providers,id'],
            'sell_rate' => ['nullable', 'numeric', 'min:0'],
            'buy_rate' => ['nullable', 'numeric', 'min:0'],
        ]);

        $existing = !empty($data['idempotency_key'])
            ? DB::table('sms_messages')->where('idempotency_key', $data['idempotency_key'])->first()
            : null;
        if ($existing) {
            return response()->json(['message_id' => $existing->message_id, 'status' => $existing->final_status], 200);
        }

        $customerId = $data['customer_id'] ?? optional($request->user())->id;
        $providerId = $data['provider_id'] ?? null;
        $resolved = $this->resolveRates($data['to'], $customerId, $providerId);
        $sellRate = $resolved['sell_rate'] ?? ($data['sell_rate'] ?? 0);
        $buyRate = $resolved['buy_rate'] ?? ($data['buy_rate'] ?? 0);
        $currency = $resolved['sell_currency'] ?? config('smpp.currency', 'BDT');

        $messageId = (string) Str::uuid();
        $smsId = DB::table('sms_messages')->insertGetId([
            'message_id' => $messageId,
            'source' => $data['from'],
            'destination' => $data['to'],
            'message' => $data['content'],
            'customer_id' => $customerId,
            'provider_id' => $providerId,
            'sell_rate' => $sellRate,
            'buy_rate' => $buyRate,
            'segments' => max(1, (int) ceil(mb_strlen($data['content']) / 160)),
            'final_status' => 'UNKNOWN',
            'currency' => $currency,
            'idempotency_key' => $data['idempotency_key'] ?? null,
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        SendSmsToGateway::dispatch($smsId);
        return response()->json(['message_id' => $messageId, 'status' => 'QUEUED'], 202);
    }

    private function resolveRates(string $destination, ?int $customerId, ?int $providerId): ?array
    {
        $digits = preg_replace('/\D+/', '', $destination);
        $rates = DB::table('rates')
            ->whereRaw('UPPER(type) = ?', ['SMS'])
            ->where(function ($query) use ($customerId) {
                $query->whereNull('customer_id');
                if ($customerId) $query->orWhere('customer_id', $customerId);
            })
            ->where(function ($query) use ($providerId) {
                $query->whereNull('provider_id');
                if ($providerId) $query->orWhere('provider_id', $providerId);
            })
            ->where(function ($query) {
                $query->whereNull('effective_until')->orWhere('effective_until', '>=', now());
            })
            ->where('effective_from', '<=', now())
            ->get();

        $best = null;
        $bestScore = -1;
        foreach ($rates as $rate) {
            $prefix = preg_replace('/\D+/', '', (string) ($rate->prefix ?? ''));
            if ($prefix !== '' && !str_starts_with($digits, $prefix)) continue;
            if ($rate->customer_id !== null && (int) $rate->customer_id !== (int) $customerId) continue;
            if ($rate->provider_id !== null && (int) $rate->provider_id !== (int) $providerId) continue;
            $score = strlen($prefix) * 100 + ($rate->customer_id !== null ? 10 : 0) + ($rate->provider_id !== null ? 10 : 0);
            if ($score > $bestScore) {
                $best = [
                    'buy_rate' => (float) $rate->buy_rate,
                    'sell_rate' => (float) $rate->sell_rate,
                    'buy_currency' => $rate->buy_currency ?? $rate->currency,
                    'sell_currency' => $rate->sell_currency ?? $rate->currency,
                ];
                $bestScore = $score;
            }
        }
        return $best;
    }

    public function status(string $messageId)
    {
        $message = DB::table('sms_messages')->where('message_id', $messageId)->firstOrFail();
        return response()->json(['message_id' => $message->message_id, 'status' => $message->final_status, 'provider_status' => $message->provider_status]);
    }

}
