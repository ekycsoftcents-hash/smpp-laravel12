<?php

namespace App\Jobs;

use App\Services\Billing\BillingService;
use App\Services\Jasmin\JasminHttpAdapter;
use App\Services\Jasmin\JasminSmppProvisioningService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class SendSmsToJasmin implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 45;

    public function __construct(public readonly int $smsId)
    {
        $this->onQueue('sms-submit');
    }

    public function backoff(): array
    {
        return [5, 15, 45, 120];
    }

    public function handle(JasminHttpAdapter $jasmin, BillingService $billing, JasminSmppProvisioningService $provisioning): void
    {
        $message = DB::table('sms_messages')->where('id', $this->smsId)->first();
        if (!$message || in_array($message->final_status, ['DELIVERED', 'FAILED', 'REJECTED'], true)) {
            return;
        }

        if ($message->customer_id) {
            $customer = DB::table('users')->where('id', $message->customer_id)->first();
            $account = DB::table('customer_smpp_accounts')->where('user_id', $message->customer_id)->first();
            if (!$customer || !$account || !(bool) $account->enabled || (float) $customer->balance < 1.0) {
                if ($account && (bool) $account->enabled && (float) ($customer->balance ?? 0) < 1.0) {
                    $provisioning->disable('u' . $message->customer_id);
                    DB::table('customer_smpp_accounts')->where('user_id', $message->customer_id)->update(['enabled' => false, 'updated_at' => now()]);
                }
                DB::table('sms_messages')->where('id', $this->smsId)->update([
                    'final_status' => 'REJECTED',
                    'provider_status' => 'BALANCE_BLOCKED',
                    'metadata' => json_encode(['error' => 'Customer balance is below 1.00 or SMPP account is disabled.']),
                    'updated_at' => now(),
                ]);
                return;
            }
        }

        $jasminId = $jasmin->send([
            'destination' => $message->destination,
            'source' => $message->source,
            'content' => $message->message,
            'coding' => str_contains($message->message, '?') ? 1 : 0,
            'dlr_level' => 2,
        ]);

        DB::table('sms_messages')->where('id', $this->smsId)->update([
            'provider_status' => 'QUEUED',
            'final_status' => 'SUBMITTED',
            'provider_submitted_at' => now(),
            'submitted_at' => $message->submitted_at ?: now(),
            'metadata' => json_encode(array_merge(json_decode($message->metadata ?: '{}', true) ?: [], ['jasmin_message_id' => $jasminId])),
            'updated_at' => now(),
        ]);

        $billing->onSubmission($this->smsId);
    }

    public function failed(Throwable $exception): void
    {
        DB::table('sms_messages')->where('id', $this->smsId)->update([
            'final_status' => 'FAILED',
            'provider_status' => 'QUEUE_FAILED',
            'metadata' => json_encode(['error' => $exception->getMessage()]),
            'updated_at' => now(),
        ]);
    }
}
