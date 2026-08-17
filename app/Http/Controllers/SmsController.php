<?php

namespace App\Http\Controllers;

use App\Jobs\SendSmsToJasmin;
use App\Services\Billing\BillingService;
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

        $messageId = (string) Str::uuid();
        $smsId = DB::table('sms_messages')->insertGetId([
            'message_id' => $messageId,
            'source' => $data['from'],
            'destination' => $data['to'],
            'message' => $data['content'],
            'customer_id' => $data['customer_id'] ?? optional($request->user())->id,
            'provider_id' => $data['provider_id'] ?? null,
            'sell_rate' => $data['sell_rate'] ?? 0,
            'buy_rate' => $data['buy_rate'] ?? 0,
            'segments' => max(1, (int) ceil(mb_strlen($data['content']) / 160)),
            'final_status' => 'UNKNOWN',
            'currency' => config('smpp.currency', 'BDT'),
            'idempotency_key' => $data['idempotency_key'] ?? null,
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        SendSmsToJasmin::dispatch($smsId);
        return response()->json(['message_id' => $messageId, 'status' => 'QUEUED'], 202);
    }

    public function status(string $messageId)
    {
        $message = DB::table('sms_messages')->where('message_id', $messageId)->firstOrFail();
        return response()->json(['message_id' => $message->message_id, 'status' => $message->final_status, 'provider_status' => $message->provider_status]);
    }

    public function jasminDlr(Request $request, BillingService $billing)
    {
        $id = $request->input('id') ?: $request->input('message_id');
        $status = strtoupper((string) ($request->input('message_status') ?: $request->input('status')));
        $mapped = match (true) {
            in_array($status, ['DELIVRD', 'DELIVERED'], true) => 'DELIVERED',
            in_array($status, ['UNDELIV', 'FAILED', 'REJECTD', 'REJECTED'], true) => 'FAILED',
            in_array($status, ['EXPIRED', 'EXPIRD'], true) => 'EXPIRED',
            default => 'UNKNOWN',
        };

        if ($id) {
            $message = DB::table('sms_messages')->whereJsonContains('metadata->jasmin_message_id', $id)->first();
            if (!$message) {
                $message = DB::table('sms_messages')->where('message_id', $id)->first();
            }
            if ($message) {
                DB::table('sms_messages')->where('id', $message->id)->update([
                    'final_status' => $mapped,
                    'provider_status' => $status ?: 'UNKNOWN',
                    'customer_status' => $mapped,
                    'dlr_at' => now(),
                    'updated_at' => now(),
                ]);
                $billing->onDlr((int) $message->id, $mapped);
            }
        }

        return response()->json(['accepted' => true, 'status' => $mapped]);
    }
}
