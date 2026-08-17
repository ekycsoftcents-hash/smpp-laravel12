<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use App\Events\CustomerBalanceBelowThreshold;
use App\Listeners\SendLowBalanceAlert;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(CustomerBalanceBelowThreshold::class, SendLowBalanceAlert::class);
    }
}
