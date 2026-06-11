<?php

namespace App\Providers;

use App\Core\Contracts\BinanceBrokerInterface;
use App\Core\Contracts\HistoricalDataProviderInterface;
use App\Infrastructure\Binance\BinanceBroker;
use App\Infrastructure\MarketData\StaticHistoricalDataProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(BinanceBrokerInterface::class, BinanceBroker::class);
        $this->app->bind(HistoricalDataProviderInterface::class, StaticHistoricalDataProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
