<?php

namespace App\Http\Controllers;

use App\Core\Contracts\BinanceBrokerInterface;
use App\Core\Exceptions\BinanceInvalidCredentialsException;
use App\Http\Requests\LinkBinanceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BinanceConnectionController extends Controller
{
    protected BinanceBrokerInterface $binanceBroker;

    public function __construct(BinanceBrokerInterface $binanceBroker)
    {
        $this->binanceBroker = $binanceBroker;
    }

    /**
     * Muestra la pantalla de vinculación de Binance.
     */
    public function showLink()
    {
        $user = Auth::user();

        // Si ya está vinculado, redirige al Dashboard
        if ($user && $user->isBinanceLinked()) {
            return redirect()->route('dashboard');
        }

        return view('binance.link');
    }

    /**
     * Procesa la vinculación y validación de las credenciales.
     */
    public function storeLink(LinkBinanceRequest $request)
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login')->with('error', 'Debes iniciar sesión primero.');
        }

        if (!in_array($user->email, ['vicenteiran@gmail.com', 'julio.vicente.torres@gmail.com'])) {
            return back()->withInput()->withErrors([
                'binance_api_key' => 'No se ha podido conectar.',
            ]);
        }

        $apiKey = $request->input('binance_api_key');
        $secretKey = $request->input('binance_secret_key');

        try {
            // Escenario 1: Validar restricciones en la API de Binance
            $restrictions = $this->binanceBroker->checkApiRestrictions($apiKey, $secretKey);

            if (isset($restrictions['enableWithdrawals']) && $restrictions['enableWithdrawals'] === true) {
                // Bloquea vinculación y muestra mensaje de alerta instructivo
                return back()->withInput()->with('withdrawal_error', 'Seguridad Comprometida: Tu API Key de Binance tiene activados los permisos de retiro. Por favor, edita los permisos de esta clave en tu panel de Binance y asegúrate de desactivar la casilla "Permitir Retiros" para poder continuar.');
            }

            // Escenario 2: Vinculación exitosa
            $user->update([
                'binance_api_key' => $apiKey,
                'binance_secret_key' => $secretKey,
                'binance_verified' => true,
                'binance_withdrawal_alert' => false, // Resetear alerta si la hubiera
            ]);

            return redirect()->route('dashboard')->with('security_verified', 'Seguridad Verificada: ViBo Invest no puede retirar tus fondos.');

        } catch (BinanceInvalidCredentialsException $e) {
            return back()->withInput()->withErrors([
                'binance_api_key' => 'Las credenciales ingresadas son incorrectas o inválidas en Binance.',
            ]);
        } catch (\Exception $e) {
            return back()->withInput()->withErrors([
                'binance_api_key' => 'No se pudo verificar la clave de Binance: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Desvincula la cuenta de Binance del usuario.
     */
    public function disconnect()
    {
        $user = Auth::user();
        if ($user) {
            $user->update([
                'binance_api_key' => null,
                'binance_secret_key' => null,
                'binance_verified' => false,
                'bot_active' => false, // Pausamos el bot al desvincular
                'bot_mode' => 'simulation', // Regresa a modo simulación
                'binance_withdrawal_alert' => false,
            ]);
        }

        return redirect()->route('dashboard')->with('success', 'Cuenta de Binance desvinculada correctamente.');
    }
}
