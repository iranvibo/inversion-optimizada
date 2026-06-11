<?php

namespace App\Providers;

use App\Core\Contracts\BinanceBrokerInterface;
use App\Infrastructure\Binance\BinanceBroker;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(BinanceBrokerInterface::class, BinanceBroker::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
