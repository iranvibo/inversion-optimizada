<?php

namespace App\Http\Controllers;

use App\Core\Contracts\HyperliquidBrokerInterface;
use App\Core\Exceptions\HyperliquidInvalidCredentialsException;
use App\Http\Requests\LinkHyperliquidRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Vinculación del canal de ejecución Hyperliquid (DEX de perpetuos on-chain).
 *
 * El usuario aporta la dirección de su wallet principal y la clave privada de
 * una API wallet (agente) aprobada en Hyperliquid. La promesa de seguridad es
 * la misma que con Binance: la credencial almacenada NO puede retirar fondos.
 * Si el usuario pega por error la clave de su wallet principal (que sí podría
 * retirar), la vinculación se rechaza.
 */
class HyperliquidConnectionController extends Controller
{
    public function __construct(
        protected readonly HyperliquidBrokerInterface $hyperliquidBroker,
    ) {}

    /**
     * Muestra la pantalla de vinculación de Hyperliquid.
     */
    public function showLink()
    {
        $user = Auth::user();

        // Si ya está vinculado, redirige al Dashboard
        if ($user && $user->isHyperliquidLinked()) {
            return redirect()->route('dashboard');
        }

        return view('hyperliquid.link');
    }

    /**
     * Procesa la vinculación y validación de las credenciales.
     */
    public function storeLink(LinkHyperliquidRequest $request)
    {
        $user = Auth::user();
        if (! $user) {
            return redirect()->route('login')->with('error', 'Debes iniciar sesión primero.');
        }

        // Misma lista de control que Binance durante la fase de pruebas privadas.
        if (! in_array($user->email, ['vicenteiran@gmail.com', 'julio.vicente.torres@gmail.com'])) {
            return back()->withInput()->withErrors([
                'hyperliquid_wallet_address' => 'No se ha podido conectar.',
            ]);
        }

        $walletAddress = strtolower(trim($request->input('hyperliquid_wallet_address')));
        $agentKey = trim($request->input('hyperliquid_agent_key'));

        try {
            $restrictions = $this->hyperliquidBroker->checkApiRestrictions($walletAddress, $agentKey);

            if (($restrictions['enableWithdrawals'] ?? false) === true) {
                // La clave pegada controla la wallet principal: podría retirar fondos.
                return back()->withInput()->with('withdrawal_error', 'Seguridad Comprometida: La clave privada que has introducido corresponde a tu wallet principal, que SÍ puede retirar fondos. Por seguridad, crea una API wallet (agente) en el panel de API de Hyperliquid y pega aquí la clave privada de esa API wallet, nunca la de tu wallet principal.');
            }

            $user->update([
                'hyperliquid_wallet_address' => $walletAddress,
                'hyperliquid_agent_key' => $agentKey,
                'hyperliquid_verified' => true,
            ]);

            return redirect()->route('dashboard')->with('security_verified', 'Seguridad Verificada: la API wallet de Hyperliquid solo puede operar; ViBo Invest no puede retirar tus fondos.');

        } catch (HyperliquidInvalidCredentialsException $e) {
            return back()->withInput()->withErrors([
                'hyperliquid_wallet_address' => $e->getMessage(),
            ]);
        } catch (\Exception $e) {
            return back()->withInput()->withErrors([
                'hyperliquid_wallet_address' => 'No se pudo verificar la conexión con Hyperliquid: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Desvincula la wallet de Hyperliquid del usuario. Si era el canal de
     * ejecución activo, el bot se pausa y se vuelve a simulación con Binance
     * como canal por defecto (mismo criterio de seguridad que Binance).
     */
    public function disconnect()
    {
        $user = Auth::user();
        if ($user) {
            $wasActiveChannel = $user->tradingChannel() === User::CHANNEL_HYPERLIQUID;

            $user->update([
                'hyperliquid_wallet_address' => null,
                'hyperliquid_agent_key' => null,
                'hyperliquid_verified' => false,
                ...($wasActiveChannel ? [
                    'trading_channel' => User::CHANNEL_BINANCE,
                    'bot_active' => false,
                    'bot_mode' => 'simulation',
                ] : []),
            ]);
        }

        return redirect()->route('dashboard')->with('success', 'Wallet de Hyperliquid desvinculada correctamente.');
    }
}
