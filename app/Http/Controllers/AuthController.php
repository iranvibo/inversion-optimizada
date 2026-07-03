<?php

namespace App\Http\Controllers;

use App\Core\Contracts\BinanceBrokerInterface;
use App\Core\Contracts\HyperliquidBrokerInterface;
use App\Models\InvitationCode;
use App\Models\User;
use App\Services\FirebaseTokenVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
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
            'invitation_code' => ['required', 'string', 'max:255'],
            'privacy' => ['accepted'],
            'terms' => ['accepted'],
        ], [
            'privacy.accepted' => 'Debes aceptar la Política de Privacidad para continuar.',
            'terms.accepted' => 'Debes aceptar los Términos de Servicio y el Descargo de Responsabilidad para continuar.',
        ], [
            'name' => 'nombre',
            'email' => 'correo electrónico',
            'password' => 'contraseña',
            'invitation_code' => 'código de invitación',
        ]);

        $user = $this->createInvitedUser($data['email'], $data['invitation_code'], [
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
            'invitation_code' => ['sometimes', 'nullable', 'string', 'max:255'],
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
            // Iniciar sesión en una cuenta ya creada no requiere invitación.
            $user->forceFill([
                'firebase_uid' => $claims['sub'],
                'avatar' => $claims['picture'] ?? $user->avatar,
                'accepted_privacy_at' => $user->accepted_privacy_at ?? now(),
                'accepted_terms_at' => $user->accepted_terms_at ?? now(),
            ])->save();
        } else {
            // Cuenta nueva: el registro exige un código de invitación emitido
            // para el email verificado por Google. Sin email verificado no hay
            // forma segura de casarlo con una invitación, así que se rechaza.
            if (! $email) {
                return response()->json([
                    'message' => 'Tu cuenta de Google no tiene un correo verificado, así que no podemos validar tu invitación. Regístrate con correo y contraseña.',
                ], 422);
            }

            try {
                $user = $this->createInvitedUser($email, (string) $request->string('invitation_code'), [
                    'name' => $claims['name'] ?? Str::before($email, '@'),
                    'email' => $email,
                    'firebase_uid' => $claims['sub'],
                    'avatar' => $claims['picture'] ?? null,
                    'accepted_privacy_at' => now(),
                    'accepted_terms_at' => now(),
                ]);
            } catch (ValidationException $e) {
                return response()->json([
                    'message' => 'Necesitas un código de invitación válido para crear tu cuenta. Introdúcelo en el formulario de registro y vuelve a intentarlo.',
                ], 422);
            }
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return response()->json([
            'redirect' => $this->afterLoginUrl(),
        ]);
    }

    /**
     * Crea un usuario canjeando su código de invitación de forma atómica.
     * El registro sólo es válido con un código vigente emitido para ese email;
     * si el código no existe, caducó o ya se usó, se aborta con un error de
     * validación sobre el campo invitation_code.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException
     */
    private function createInvitedUser(string $email, string $plainCode, array $attributes): User
    {
        $invitation = InvitationCode::findRedeemable($email, $plainCode);

        if (! $invitation) {
            throw ValidationException::withMessages([
                'invitation_code' => 'El código de invitación no es válido para este correo o ya no está vigente.',
            ]);
        }

        return DB::transaction(function () use ($invitation, $attributes) {
            $user = User::create($attributes);

            // Si otro registro simultáneo ganó la carrera por el mismo código,
            // la transacción revierte también la creación del usuario.
            if (! $invitation->redeemFor($user)) {
                throw ValidationException::withMessages([
                    'invitation_code' => 'El código de invitación no es válido para este correo o ya no está vigente.',
                ]);
            }

            return $user;
        });
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
     * Cierra de forma preventiva cualquier posición abierta en los canales
     * de ejecución vinculados (Binance y/o Hyperliquid) si el bot está activo.
     */
    public function deleteAccount(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->bot_active) {
            // Cada canal se cierra de forma independiente: el fallo de uno no
            // impide intentar cerrar el otro ni bloquea la eliminación (GDPR).
            if ($user->isBinanceLinked()) {
                try {
                    app(BinanceBrokerInterface::class)
                        ->closeOpenPositions($user->binance_api_key, $user->binance_secret_key);
                    Log::info("Cierre de posiciones preventivo (Binance) por eliminación de cuenta para el usuario ID: {$user->id}");
                } catch (\Exception $e) {
                    Log::critical("Fallo al cerrar posiciones en Binance al eliminar la cuenta del usuario ID: {$user->id}. Detalle: ".$e->getMessage());
                }
            }

            if ($user->isHyperliquidLinked()) {
                try {
                    app(HyperliquidBrokerInterface::class)
                        ->closeOpenPositions($user->hyperliquid_wallet_address, $user->hyperliquid_agent_key);
                    Log::info("Cierre de posiciones preventivo (Hyperliquid) por eliminación de cuenta para el usuario ID: {$user->id}");
                } catch (\Exception $e) {
                    Log::critical("Fallo al cerrar posiciones en Hyperliquid al eliminar la cuenta del usuario ID: {$user->id}. Detalle: ".$e->getMessage());
                }
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
