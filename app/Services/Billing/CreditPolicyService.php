<?php

namespace App\Services\Billing;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class CreditPolicyService
{
    public function authorizeCustomer(int $userId, string $amount, string $currency): void
    {
        $user = DB::table('users')->lockForUpdate()->find($userId);
        if (!$user) throw new RuntimeException('Customer account not found.');
        $policy = strtoupper($user->billing_policy ?? 'PREPAID');
        if ($policy === 'DUE') {
            $available = bcadd((string) $user->credit_limit, bcmul((string) $user->balance, '-1', 6), 6);
            $available = bcsub($available, (string) ($user->credit_used ?? 0), 6);
            if (bccomp($available, $amount, 6) < 0) throw new RuntimeException('Customer credit limit exceeded.');
            return;
        }
        if (bccomp((string) $user->balance, $amount, 6) < 0) throw new RuntimeException('Insufficient prepaid balance.');
    }

    public function authorizeProvider(int $providerId, string $amount, string $currency): void
    {
        $provider = DB::table('providers')->lockForUpdate()->find($providerId);
        if (!$provider) throw new RuntimeException('Provider account not found.');
        $policy = strtoupper($provider->settlement_policy ?? 'DUE');
        if ($policy === 'PREPAID' && bccomp((string) $provider->credit_used, $amount, 6) >= 0) {
            throw new RuntimeException('Provider prepaid balance exhausted.');
        }
    }

    public function postCustomer(int $userId, string $amount, string $currency, string $reference, string $description): void
    {
        $user = DB::table('users')->lockForUpdate()->find($userId);
        if (strtoupper($user->billing_policy ?? 'PREPAID') === 'DUE') {
            DB::table('users')->where('id', $userId)->increment('credit_used', $amount);
            $dueAt = now()->addDays((int) ($user->payment_terms_days ?? 30));
            DB::table('users')->where('id', $userId)->update(['credit_due_at' => $dueAt, 'updated_at' => now()]);
        } else {
            DB::table('users')->where('id', $userId)->decrement('balance', $amount);
        }
        DB::table('credit_transactions')->insert(['account_type' => 'CUSTOMER', 'account_id' => $userId, 'currency' => $currency, 'transaction_type' => 'CHARGE', 'amount' => $amount, 'reference' => $reference, 'description' => $description, 'created_at' => now(), 'updated_at' => now()]);
    }
}
