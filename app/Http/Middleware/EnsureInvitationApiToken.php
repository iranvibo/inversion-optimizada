<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentica la API de invitaciones con un token Bearer estático
 * (INVITATION_API_TOKEN). Pensada para consumo servidor-a-servidor
 * (backoffice / herramientas internas), no desde el navegador.
 */
class EnsureInvitationApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('invitations.api_token');

        // Sin token configurado la API queda deshabilitada (fail closed):
        // nunca debe quedar abierta por una variable de entorno ausente.
        if (empty($expected)) {
            abort(503, 'La API de invitaciones no está configurada.');
        }

        $provided = $request->bearerToken();

        // hash_equals evita filtrar información por tiempos de comparación.
        if (! is_string($provided) || ! hash_equals($expected, $provided)) {
            abort(401, 'Token de autenticación inválido.');
        }

        return $next($request);
    }
}
