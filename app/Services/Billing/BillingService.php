<?php

namespace App\Services\Billing;

use Illuminate\Support\Facades\DB;
use App\Services\Jasmin\JasminSmppProvisioningService;
use RuntimeException;

class BillingService
{
    public function __construct(private readonly JasminSmppProvisioningService $jasmin)
    {
    }
    public function onSubmission(int $smsId): void
    {
        DB::transaction(function () use ($smsId) {
            $sms = DB::table('sms_messages')->where('id', $smsId)->lockForUpdate()->first();
            if (!$sms) return;

            $customerMode = $this->customerMode($sms->customer_id);
            $providerMode = $this->providerMode($sms->provider_id);
            $sell = (float) $sms->sell_rate * max(1, (int) $sms->segments);
            $buy = (float) $sms->buy_rate * max(1, (int) $sms->segments);

            if ($customerMode === 'SUBMISSION' && $sms->customer_id && $sell > 0) {
                $this->postCustomerDebit($sms, $sell, 'SMS_SUBMISSION_CHARGE', "Submission charge for {$sms->message_id}");
            }
            if ($providerMode === 'SUBMISSION' && $sms->provider_id && $buy > 0) {
                $this->postProviderCredit($sms, $buy, 'PROVIDER_SUBMISSION_COST', "Provider submission cost for {$sms->message_id}");
            }
            $this->refreshProfit($sms->id);
        });
    }

    public function onDlr(int $smsId, string $status): void
    {
        DB::transaction(function () use ($smsId, $status) {
            $sms = DB::table('sms_messages')->where('id', $smsId)->lockForUpdate()->first();
            if (!$sms) return;

            $customerMode = $this->customerMode($sms->customer_id);
            $providerMode = $this->providerMode($sms->provider_id);
            $sell = (float) $sms->sell_rate * max(1, (int) $sms->segments);
            $buy = (float) $sms->buy_rate * max(1, (int) $sms->segments);
            $success = $status === 'DELIVERED';
            $refundable = in_array($status, ['FAILED', 'REJECTED', 'EXPIRED'], true);

            if ($customerMode === 'DLR' && $sms->customer_id && $success && $sell > 0) {
                $this->postCustomerDebit($sms, $sell, 'SMS_DLR_CHARGE', "DLR charge for {$sms->message_id}");
            }
            if ($customerMode === 'SUBMISSION' && $sms->customer_id && $refundable && $sell > 0) {
                $this->postCustomerCredit($sms, $sell, 'SMS_REFUND', "Refund for {$status} message {$sms->message_id}");
            }
            if ($providerMode === 'DLR' && $sms->provider_id && $success && $buy > 0) {
                $this->postProviderCredit($sms, $buy, 'PROVIDER_DLR_COST', "Provider DLR cost for {$sms->message_id}");
            }
            if ($providerMode === 'SUBMISSION' && $sms->provider_id && $refundable && $buy > 0) {
                $this->postProviderDebit($sms, $buy, 'PROVIDER_REFUND', "Provider refund for {$status} message {$sms->message_id}");
            }
            $this->refreshProfit($sms->id);
        });
    }

    private function customerMode(?int $customerId): string
    {
        return strtoupper((string) (DB::table('users')->where('id', $customerId)->value('customer_billing_mode') ?: 'SUBMISSION'));
    }

    private function providerMode(?int $providerId): string
    {
        return strtoupper((string) (DB::table('providers')->where('id', $providerId)->value('billing_mode') ?: 'SUBMISSION'));
    }

    private function postCustomerDebit(object $sms, float $amount, string $type, string $description): void
    {
        $this->postLedger($sms, $amount, 0, 'CUSTOMER', $type, $description, $sms->customer_id, null);
    }

    private function postCustomerCredit(object $sms, float $amount, string $type, string $description): void
    {
        $this->postLedger($sms, 0, $amount, 'CUSTOMER', $type, $description, $sms->customer_id, null);
    }

    private function postProviderCredit(object $sms, float $amount, string $type, string $description): void
    {
        $this->postLedger($sms, $amount, 0, 'PROVIDER', $type, $description, null, $sms->provider_id);
    }

    private function postProviderDebit(object $sms, float $amount, string $type, string $description): void
    {
        $this->postLedger($sms, 0, $amount, 'PROVIDER', $type, $description, null, $sms->provider_id);
    }

    private function postLedger(object $sms, float $debit, float $credit, string $side, string $type, string $description, ?int $accountId, ?int $providerId): void
    {
        $eventKey = strtolower($side . ':' . $type . ':' . $sms->id);
        if (DB::table('billing_events')->where('event_key', $eventKey)->exists()) return;

        $balanceAfter = $accountId
            ? (float) DB::table('users')->where('id', $accountId)->lockForUpdate()->value('balance') - $debit + $credit
            : 0;

        if ($accountId) {
            DB::table('users')->where('id', $accountId)->update(['balance' => $balanceAfter, 'updated_at' => now()]);
            if ($debit > 0 && $balanceAfter < 1.0) {
                $account = DB::table('customer_smpp_accounts')->where('user_id', $accountId)->lockForUpdate()->first();
                if ($account && (bool) $account->enabled) {
                    $this->jasmin->disable('u' . $accountId);
                    DB::table('customer_smpp_accounts')->where('user_id', $accountId)->update(['enabled' => false, 'updated_at' => now()]);
                }
            }
        }
        DB::table('ledger_entries')->insert([
            'account_id' => $accountId, 'provider_id' => $providerId, 'sms_message_id' => $sms->id,
            'entry_type' => $type, 'side' => $side, 'debit' => $debit, 'credit' => $credit,
            'balance_after' => $balanceAfter, 'currency' => $sms->currency ?: config('smpp.currency', 'BDT'),
            'reference' => $sms->message_id, 'event_key' => $eventKey, 'description' => $description,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('billing_events')->insert([
            'sms_message_id' => $sms->id, 'event_key' => $eventKey, 'side' => $side,
            'event_type' => $type, 'amount' => $debit ?: $credit, 'currency' => $sms->currency ?: config('smpp.currency', 'BDT'),
            'status' => 'POSTED', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function refreshProfit(int $smsId): void
    {
        $sms = DB::table('sms_messages')->where('id', $smsId)->first();
        $customerRevenue = (float) DB::table('billing_events')->where('sms_message_id', $smsId)->where('side', 'CUSTOMER')->whereIn('event_type', ['SMS_SUBMISSION_CHARGE', 'SMS_DLR_CHARGE'])->sum('amount');
        $customerRefund = (float) DB::table('billing_events')->where('sms_message_id', $smsId)->where('side', 'CUSTOMER')->where('event_type', 'SMS_REFUND')->sum('amount');
        $providerCost = (float) DB::table('billing_events')->where('sms_message_id', $smsId)->where('side', 'PROVIDER')->whereIn('event_type', ['PROVIDER_SUBMISSION_COST', 'PROVIDER_DLR_COST'])->sum('amount');
        $providerRefund = (float) DB::table('billing_events')->where('sms_message_id', $smsId)->where('side', 'PROVIDER')->where('event_type', 'PROVIDER_REFUND')->sum('amount');
        $revenue = $customerRevenue - $customerRefund;
        $cost = $providerCost - $providerRefund;
        DB::table('sms_messages')->where('id', $smsId)->update(['customer_charge' => $revenue, 'provider_cost' => $cost, 'profit' => $revenue - $cost, 'updated_at' => now()]);
    }
}
