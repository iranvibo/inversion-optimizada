<?php

namespace App\Console\Commands;

use App\Core\Contracts\BinanceBrokerInterface;
use App\Core\Exceptions\BinanceInvalidCredentialsException;
use App\Events\BalanceUpdated;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Sincronización periódica del balance de Binance (US03, Escenario 3):
 * consulta el balance consolidado de cada usuario vinculado, persiste un
 * snapshot de la serie temporal y emite el evento reactivo al dashboard.
 */
class SyncBinanceBalances extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'binance:sync-balances';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza el balance consolidado de Binance de los usuarios vinculados, guarda un snapshot histórico y notifica al dashboard vía broadcast.';

    public function __construct(
        protected BinanceBrokerInterface $binanceBroker,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Iniciando sincronización de balances de Binance...');

        $users = User::where('binance_verified', true)
            ->whereNotNull('binance_api_key')
            ->whereNotNull('binance_secret_key')
            ->get();

        if ($users->isEmpty()) {
            $this->info('No hay usuarios con Binance vinculado para sincronizar.');

            return self::SUCCESS;
        }

        $syncedCount = 0;

        foreach ($users as $user) {
            try {
                $balance = $this->binanceBroker->getTotalBalance(
                    $user->binance_api_key,
                    $user->binance_secret_key,
                );

                $capturedAt = now();

                $user->balanceSnapshots()->create([
                    'balance' => $balance,
                    'captured_at' => $capturedAt,
                ]);

                event(new BalanceUpdated($user, $balance, $capturedAt->toImmutable()));

                $this->info("Balance sincronizado para {$user->email}: {$balance} EUR");
                $syncedCount++;

            } catch (BinanceInvalidCredentialsException $e) {
                // Credenciales revocadas: no se pausa el bot aquí (responsabilidad
                // de la auditoría de permisos); solo se omite el snapshot.
                $this->warn("Credenciales inválidas para el usuario ID: {$user->id}. Snapshot omitido.");
                Log::warning("Sincronización de balance omitida para usuario ID: {$user->id}: credenciales inválidas.");

            } catch (\Exception $e) {
                // Errores temporales de red/API: se registran sin abortar el lote.
                $this->error("Error al sincronizar al usuario ID: {$user->id}: ".$e->getMessage());
                Log::error("Sincronización de balance fallida para usuario ID: {$user->id}. Detalle: ".$e->getMessage());
            }
        }

        $this->info("Sincronización completada. Usuarios sincronizados: {$syncedCount} de {$users->count()}.");

        return self::SUCCESS;
    }
}
