<?php

namespace App\Http\Controllers;

use App\Models\InvitationCode;
use App\Models\User;
use App\Services\UserAccountDeleter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Administración de usuarios registrados (pestaña "Usuarios" del dashboard).
 * Todas las rutas están protegidas por el middleware 'can:admin'.
 */
class AdminUserController extends Controller
{
    public function __construct(private readonly UserAccountDeleter $accountDeleter) {}

    /**
     * Lista todos los usuarios registrados en JSON. Solo expone campos no
     * sensibles: nunca credenciales de brokers ni hashes de contraseña.
     */
    public function index(): JsonResponse
    {
        // Se cargan también las columnas de credenciales para que
        // isBrokerLinked() funcione, pero JAMÁS se serializan: el map de
        // abajo define explícitamente los únicos campos expuestos.
        $users = User::query()
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'total' => $users->count(),
            'users' => $users->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'is_admin' => $user->isAdmin(),
                'bot_active' => (bool) $user->bot_active,
                'bot_mode' => $user->bot_mode,
                'risk_level' => $user->risk_level,
                'trading_channel' => $user->tradingChannel(),
                'broker_linked' => $user->isBrokerLinked(),
                'onboarding_completed' => $user->hasCompletedOnboarding(),
                'created_at_formatted' => $user->created_at?->format('d/m/Y H:i'),
                'created_at_human' => $user->created_at?->diffForHumans(),
            ])->values(),
        ]);
    }

    /**
     * Elimina definitivamente a un usuario (cierra antes sus posiciones
     * abiertas). El administrador no puede eliminarse a sí mismo desde aquí;
     * para eso existe la eliminación de cuenta propia (GDPR).
     */
    public function destroy(User $user): JsonResponse
    {
        if ($user->is(Auth::user())) {
            return response()->json([
                'error' => 'No puedes eliminar tu propia cuenta desde el panel de administración.',
            ], 422);
        }

        Log::info("Eliminación de usuario por administración. Admin ID: ".Auth::id().", usuario eliminado ID: {$user->id} ({$user->email})");

        $this->accountDeleter->delete($user);

        return response()->json([
            'message' => 'Usuario eliminado de forma permanente.',
            'total' => User::count(),
        ]);
    }

    /**
     * Genera una URL con código de invitación para un correo electrónico desde el panel de administración.
     */
    public function storeInvitation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'expires_in_days' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:90'],
        ], [], [
            'email' => 'correo electrónico',
            'expires_in_days' => 'días de vigencia',
        ]);

        $email = Str::lower(trim($data['email']));

        if (User::where('email', $email)->exists()) {
            return response()->json([
                'message' => 'Ya existe un usuario registrado con ese correo electrónico.',
            ], 409);
        }

        ['invitation' => $invitation, 'code' => $code] = InvitationCode::issueFor(
            $email,
            isset($data['expires_in_days']) ? (int) $data['expires_in_days'] : null,
        );

        Log::info("Código de invitación generado desde panel de admin por Admin ID: ".Auth::id()." para {$email} (ID: {$invitation->id}).");

        return response()->json([
            'message' => 'Código de invitación generado con éxito.',
            'email' => $invitation->email,
            'code' => $code,
            'register_url' => route('register', ['invitation' => $code]),
            'expires_at' => $invitation->expires_at->toIso8601String(),
            'expires_at_formatted' => $invitation->expires_at->format('d/m/Y H:i'),
        ], 201);
    }
}
