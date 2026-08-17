<?php

namespace App\Services\Billing;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class CurrencyService
{
    public function isEnabled(string $currency): bool
    {
        return DB::table('currencies')->where('code', strtoupper($currency))->where('enabled', true)->exists();
    }

    public function rate(string $base, string $quote): string
    {
        $base = strtoupper($base); $quote = strtoupper($quote);
        if ($base === $quote) return '1.000000000000';
        $row = DB::table('exchange_rates')->where('base_currency', $base)->where('quote_currency', $quote)->where('enabled', true)->where('effective_at', '<=', now())->where(function ($query) {
            $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
        })->orderByDesc('effective_at')->first();
        if ($row) return (string) $row->rate;
        $inverse = DB::table('exchange_rates')->where('base_currency', $quote)->where('quote_currency', $base)->where('enabled', true)->where('effective_at', '<=', now())->orderByDesc('effective_at')->first();
        if ($inverse && bccomp((string) $inverse->rate, '0', 12) !== 0) return bcdiv('1', (string) $inverse->rate, 12);
        throw new RuntimeException("No exchange rate configured for {$base}/{$quote}");
    }

    public function convert(string|int|float $amount, string $base, string $quote, int $scale = 6): string
    {
        return bcmul((string) $amount, $this->rate($base, $quote), $scale);
    }
}
