<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InvitationCode;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * API interna de invitaciones: emite el código de un solo uso que habilita el
 * registro de un usuario concreto (identificado por su email).
 */
class InvitationController extends Controller
{
    /**
     * Genera un código de invitación para un email. El código en claro sólo
     * aparece en esta respuesta; en base de datos se guarda su hash.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'expires_in_days' => ['sometimes', 'integer', 'min:1', 'max:90'],
        ], [], [
            'email' => 'correo electrónico',
            'expires_in_days' => 'días de vigencia',
        ]);

        $email = Str::lower(trim($data['email']));

        // La API es interna y autenticada, así que el 409 explícito no supone
        // un riesgo de enumeración y evita emitir códigos que nunca podrán
        // canjearse (el registro exige un email sin cuenta previa).
        if (User::where('email', $email)->exists()) {
            return response()->json([
                'message' => 'Ya existe un usuario registrado con ese correo electrónico.',
            ], 409);
        }

        ['invitation' => $invitation, 'code' => $code] = InvitationCode::issueFor(
            $email,
            $data['expires_in_days'] ?? null,
        );

        // Se audita la emisión sin registrar jamás el código en claro.
        Log::info("Código de invitación emitido para {$email} (ID: {$invitation->id}, caduca: {$invitation->expires_at->toIso8601String()}).");

        return response()->json([
            'email' => $invitation->email,
            'code' => $code,
            // Enlace directo al registro con el código ya rellenado, listo para
            // compartir con la persona invitada. Sólo lleva el código (no el
            // email) para minimizar datos personales en historiales y logs.
            'register_url' => route('register', ['invitation' => $code]),
            'expires_at' => $invitation->expires_at->toIso8601String(),
        ], 201);
    }
}
