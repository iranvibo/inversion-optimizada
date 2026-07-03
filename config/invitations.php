<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API de invitaciones
    |--------------------------------------------------------------------------
    |
    | El registro de usuarios sólo es válido con un código de invitación
    | emitido por la API interna (POST /api/invitations), autenticada con un
    | token Bearer estático. Si el token no está configurado, la API queda
    | deshabilitada (fail closed): responde 503 a cualquier petición.
    |
    */

    'api_token' => env('INVITATION_API_TOKEN'),

    // Vigencia por defecto (en días) de un código recién emitido.
    'expiry_days' => (int) env('INVITATION_EXPIRY_DAYS', 7),

];
