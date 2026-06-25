<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\FirebaseTokenVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class AuthController extends Controller
{
    /**
     * Muestra la vista de inicio de sesión.
     */
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    /**
     * Muestra la vista de registro.
     */
    public function showRegister()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.register');
    }

    /**
     * Procesa el inicio de sesión con correo y contraseña.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ], [], [
            'email' => 'correo electrónico',
            'password' => 'contraseña',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            return $this->afterLogin();
        }

        return back()->withErrors([
            'email' => 'Las credenciales no coinciden con nuestros registros.',
        ])->onlyInput('email');
    }

    /**
     * Procesa el registro con correo y contraseña.
     */
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'privacy' => ['accepted'],
            'terms' => ['accepted'],
        ], [
            'privacy.accepted' => 'Debes aceptar la Política de Privacidad para continuar.',
            'terms.accepted' => 'Debes aceptar los Términos de Servicio y el Descargo de Responsabilidad para continuar.',
        ], [
            'name' => 'nombre',
            'email' => 'correo electrónico',
            'password' => 'contraseña',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'accepted_privacy_at' => now(),
            'accepted_terms_at' => now(),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return $this->afterLogin();
    }

    /**
     * Inicia o registra una sesión a partir de un ID token de Google (Firebase).
     *
     * El SDK de Firebase en el navegador realiza el flujo de Google y envía aquí
     * el ID token resultante, que verificamos antes de crear la sesión.
     */
    public function google(Request $request, FirebaseTokenVerifier $verifier): JsonResponse
    {
        $request->validate([
            'id_token' => ['required', 'string'],
        ]);

        try {
            $claims = $verifier->verify($request->string('id_token'));
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => 'No pudimos validar tu cuenta de Google. Inténtalo de nuevo.',
            ], 422);
        }

        // Solo confiamos en el email del token si Google lo da por verificado.
        // De lo contrario no podemos usarlo para localizar/fusionar cuentas sin
        // arriesgar una apropiación de cuenta por colisión de correo.
        $emailVerified = filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $email = $emailVerified ? ($claims['email'] ?? null) : null;

        // Prioriza la identidad fuerte (firebase_uid). Solo se vincula por email
        // cuando este viene verificado por Google.
        $user = User::where('firebase_uid', $claims['sub'])->first();

        if (! $user && $email) {
            $user = User::where('email', $email)->first();
        }

        if ($user) {
            // Vincula una cuenta de correo existente con su identidad de Google.
            $user->forceFill([
                'firebase_uid' => $claims['sub'],
                'avatar' => $claims['picture'] ?? $user->avatar,
                'accepted_privacy_at' => $user->accepted_privacy_at ?? now(),
                'accepted_terms_at' => $user->accepted_terms_at ?? now(),
            ])->save();
        } else {
            // Solo se asocia el email a la cuenta nueva si Google lo verificó; en
            // caso contrario se usa un correo sintético no colisionable para no
            // chocar con (ni suplantar) una cuenta existente con ese mismo correo.
            $user = User::create([
                'name' => $claims['name'] ?? Str::before($email ?? 'Usuario', '@'),
                'email' => $email ?? $claims['sub'].'@google.local',
                'firebase_uid' => $claims['sub'],
                'avatar' => $claims['picture'] ?? null,
                'accepted_privacy_at' => now(),
                'accepted_terms_at' => now(),
            ]);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return response()->json([
            'redirect' => $this->afterLoginUrl(),
        ]);
    }

    /**
     * Cierra la sesión.
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Elimina definitivamente la cuenta del usuario autenticado (GDPR).
     * Cierra de forma preventiva cualquier posición abierta en Binance
     * si el bot está activo.
     */
    public function deleteAccount(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->bot_active) {
            try {
                $binanceBroker = app(\App\Core\Contracts\BinanceBrokerInterface::class);
                if ($user->isBinanceLinked()) {
                    $binanceBroker->closeOpenPositions($user->binance_api_key, $user->binance_secret_key);
                    Log::info("Cierre de posiciones preventivo por eliminación de cuenta para el usuario ID: {$user->id}");
                }
            } catch (\Exception $e) {
                Log::critical("Fallo al cerrar posiciones preventivamente al eliminar la cuenta del usuario ID: {$user->id}. Detalle: " . $e->getMessage());
            }
        }

        // 1. Cerrar sesión e invalidar la sesión primero para evitar que Laravel intente 
        // actualizar el remember_token en la base de datos para un usuario eliminado
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // 2. Eliminar el usuario definitivamente (borrado en cascada configurado en BD)
        $user->delete();

        return redirect()->route('login')->with('success', 'Tu cuenta y todos tus datos personales han sido eliminados de forma permanente.');
    }

    /**
     * Redirección tras autenticarse: onboarding si aún no se ha completado.
     */
    private function afterLogin()
    {
        return redirect($this->afterLoginUrl());
    }

    /**
     * URL de destino tras autenticarse.
     */
    private function afterLoginUrl(): string
    {
        if (! Auth::user()->hasCompletedOnboarding()) {
            return route('onboarding.show');
        }

        return route('dashboard');
    }
}
