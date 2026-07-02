<?php

namespace App\Console\Commands;

use App\Core\Exceptions\InvalidBrokerCredentialsInterface;
use App\Core\Trading\BrokerResolver;
use App\Events\BalanceUpdated;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Sincronización periódica del balance (US03, Escenario 3): consulta el balance
 * consolidado de cada usuario en su canal de ejecución activo (Binance o
 * Hyperliquid), persiste un snapshot de la serie temporal y emite el evento
 * reactivo al dashboard.
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
    protected $description = 'Sincroniza el balance consolidado del canal de ejecución activo (Binance o Hyperliquid) de los usuarios vinculados, guarda un snapshot histórico y notifica al dashboard vía broadcast.';

    public function __construct(
        protected BrokerResolver $brokerResolver,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Iniciando sincronización de balances del canal de ejecución...');

        // Candidatos: usuarios con algún canal verificado. El filtro fino
        // (canal ACTIVO vinculado) se aplica por usuario con isBrokerLinked().
        $users = User::where('binance_verified', true)
            ->orWhere('hyperliquid_verified', true)
            ->get()
            ->filter(fn (User $user) => $user->isBrokerLinked());

        if ($users->isEmpty()) {
            $this->info('No hay usuarios con un canal de ejecución vinculado para sincronizar.');

            return self::SUCCESS;
        }

        $syncedCount = 0;

        foreach ($users as $user) {
            // El histórico real solo se persiste con datos reales: si el canal
            // del usuario está en mock, se omite para no contaminar la curva real.
            if ($this->brokerResolver->isMock($user->tradingChannel())) {
                $this->info("Canal {$user->tradingChannel()} en modo mock: snapshot omitido para {$user->email}.");

                continue;
            }

            try {
                $balance = $this->brokerResolver->forUser($user)->getTotalBalance(
                    $user->brokerApiKey(),
                    $user->brokerSecretKey(),
                );

                $capturedAt = now();

                $user->balanceSnapshots()->create([
                    'balance' => $balance,
                    'captured_at' => $capturedAt,
                    'trading_channel' => $user->tradingChannel(),
                ]);

                event(new BalanceUpdated($user, $balance, $capturedAt->toImmutable()));

                $this->info("Balance sincronizado para {$user->email} ({$user->tradingChannel()}): {$balance}");
                $syncedCount++;

            } catch (InvalidBrokerCredentialsInterface $e) {
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
