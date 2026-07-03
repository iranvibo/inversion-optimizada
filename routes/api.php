<?php

use App\Http\Controllers\Api\InvitationController;
use App\Http\Middleware\EnsureInvitationApiToken;
use Illuminate\Support\Facades\Route;

// Emisión de códigos de invitación (servidor-a-servidor, token Bearer).
// Throttle propio además del limitador 'api' por defecto: la emisión de
// invitaciones es una operación poco frecuente y no debe poder martillearse.
Route::post('/invitations', [InvitationController::class, 'store'])
    ->middleware([EnsureInvitationApiToken::class, 'throttle:30,1'])
    ->name('api.invitations.store');
