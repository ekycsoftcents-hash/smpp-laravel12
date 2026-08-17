<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CustomerBalanceBelowThreshold
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $customerId,
        public readonly float $balance,
        public readonly float $threshold,
        public readonly string $currency,
        public readonly ?int $smsId = null,
    ) {
    }
}
