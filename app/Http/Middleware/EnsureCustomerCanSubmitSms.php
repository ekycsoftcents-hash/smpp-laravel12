<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerCanSubmitSms
{
    public function handle(Request $request, Closure $next): Response
    {
        $customerId = $request->user()?->id ?? $request->integer('customer_id');
        if (!$customerId) return $next($request);

        $customer = DB::table('users')->where('id', $customerId)->first(['id', 'balance', 'account_type']);
        $account = DB::table('customer_smpp_accounts')->where('user_id', $customerId)->first(['enabled']);
        $threshold = (float) config('alerts.balance_threshold', 1.00);

        if (!$customer || !in_array($customer->account_type, ['customer', 'reseller'], true)) {
            return response()->json(['message' => 'Customer account was not found.'], 422);
        }
        if ((float) $customer->balance < $threshold || !$account || !(bool) $account->enabled) {
            return response()->json([
                'message' => 'SMS traffic blocked: balance is below the minimum threshold or SMPP is disabled.',
                'balance' => (float) $customer->balance,
                'threshold' => $threshold,
                'code' => 'BALANCE_BLOCKED',
            ], 402);
        }

        return $next($request);
    }
}
