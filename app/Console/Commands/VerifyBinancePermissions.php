<?php

namespace App\Console\Commands;

use App\Core\Contracts\BinanceBrokerInterface;
use App\Core\Exceptions\BinanceInvalidCredentialsException;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class VerifyBinancePermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'binance:verify-permissions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audita de forma periódica las restricciones de la API Key de Binance de los usuarios con bot activo en modo real y los pausa si se detectan permisos de retiro.';

    protected BinanceBrokerInterface $binanceBroker;

    public function __construct(BinanceBrokerInterface $binanceBroker)
    {
        parent::__construct();
        $this->binanceBroker = $binanceBroker;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando auditoría de permisos de API de Binance...');

        // Seleccionamos usuarios con Binance verificado y bot activo en modo real
        // cuyo canal de ejecución sea Binance: la auditoría de permisos de retiro
        // es específica de la API de Binance y no debe pausar el bot de quien
        // opera por Hyperliquid (allí la API wallet no puede retirar por diseño).
        $users = User::where('binance_verified', true)
            ->where('bot_active', true)
            ->where('bot_mode', 'real')
            ->where('trading_channel', '!=', User::CHANNEL_HYPERLIQUID)
            ->get();

        if ($users->isEmpty()) {
            $this->info('No hay usuarios activos en modo real para auditar.');

            return 0;
        }

        $auditedCount = 0;
        $alertedCount = 0;

        foreach ($users as $user) {
            $this->info("Auditando usuario: {$user->email} (ID: {$user->id})");
            $auditedCount++;

            try {
                // Consultar restricciones en Binance
                $restrictions = $this->binanceBroker->checkApiRestrictions(
                    $user->binance_api_key,
                    $user->binance_secret_key
                );

                if (isset($restrictions['enableWithdrawals']) && $restrictions['enableWithdrawals'] === true) {
                    $this->triggerWithdrawalAlert($user);
                    $alertedCount++;
                }

            } catch (BinanceInvalidCredentialsException $e) {
                // Si las credenciales han sido revocadas, pausamos el bot por seguridad
                $this->warn("Credenciales inválidas detectadas para el usuario ID: {$user->id}. Pausando bot.");
                $user->update([
                    'bot_active' => false,
                ]);
                Log::warning("Auditoría Binance: Bot pausado para el usuario ID: {$user->id} debido a credenciales inválidas.");

            } catch (\Exception $e) {
                // Errores temporales de red o API no deben pausar el bot en real, solo logueamos
                $this->error("Error al auditar al usuario ID: {$user->id}: ".$e->getMessage());
                Log::error("Auditoría Binance fallida para usuario ID: {$user->id}. Detalle: ".$e->getMessage());
            }
        }

        $this->info("Auditoría completada. Usuarios auditados: {$auditedCount}. Alertas disparadas: {$alertedCount}.");

        return 0;
    }

    /**
     * Pausa el bot y activa la alerta de retiro para el usuario.
     */
    protected function triggerWithdrawalAlert(User $user)
    {
        $user->update([
            'bot_active' => false,
            'binance_withdrawal_alert' => true,
        ]);

        $msg = "SEGURIDAD COMPROMETIDA: Se detectaron permisos de retiro habilitados para el usuario ID: {$user->id}. El bot se pausó de forma inmediata.";
        $this->error($msg);
        Log::critical($msg);

        // Opcional: Aquí se podría disparar un evento de broadcast para actualización en vivo vía WebSockets
        // event(new \App\Events\BinanceSecurityAlert($user->id));
    }
}
