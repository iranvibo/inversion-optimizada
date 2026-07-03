<?php

namespace App\Providers;

use App\Core\Contracts\BinanceBrokerInterface;
use App\Core\Contracts\HyperliquidBrokerInterface;
use App\Core\Contracts\SignalProviderInterface;
use App\Infrastructure\Binance\BinanceBroker;
use App\Infrastructure\Hyperliquid\HyperliquidBroker;
use App\Infrastructure\Signals\HttpSignalProvider;
use App\Infrastructure\Signals\MockSignalProvider;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(BinanceBrokerInterface::class, BinanceBroker::class);
        $this->app->bind(HyperliquidBrokerInterface::class, HyperliquidBroker::class);

        $this->app->bind(SignalProviderInterface::class, function ($app) {
            $driver = config('signals.provider', 'mock');

            return $driver === 'http'
                ? $app->make(HttpSignalProvider::class)
                : $app->make(MockSignalProvider::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Acceso de administración (pestaña "Usuarios" del dashboard y sus rutas).
        Gate::define('admin', fn (User $user) => $user->isAdmin());
    }
}
