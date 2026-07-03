---
created: 2026-06-16
updated: 2026-07-03
---

# Autenticación: contraseña, Google (Firebase), privacidad, invitaciones y eliminación de cuenta (GDPR)

Ampliación del flujo de login/registro de ViBo Invest (US01), cumplimiento GDPR y el derecho al olvido mediante eliminación definitiva de la cuenta. Desde 2026-07-03 el registro es **sólo con código de invitación**.

## Registro sólo con invitación (2026-07-03)

- *Decisión*: Nadie puede registrarse (contraseña **ni** Google) sin un código de invitación emitido para su email concreto por la API interna `POST /api/invitations` (nueva `routes/api.php`, registrada en `bootstrap/app.php`). Autenticada con Bearer estático `INVITATION_API_TOKEN` (comparación `hash_equals`; **fail closed**: sin la env responde 503) + `throttle:30,1`. Devuelve 409 si el email ya tiene cuenta. La respuesta incluye `register_url` (`/register?invitation=CODE`, sin email para minimizar datos personales en logs/historial): la vista de registro precarga el campo con `old('invitation_code', request()->query('invitation'))`.
- *Modelo `InvitationCode`* (tabla `invitation_codes`): el código en claro **sólo** viaja una vez en la respuesta de la API; en BD se guarda su SHA-256 (`code_hash`, unique). Formato `XXXX-XXXX-XXXX-XXXX` (alfabeto de 32 símbolos sin 0/O/1/I ⇒ 80 bits); el canje normaliza mayúsculas/guiones/espacios. Un solo código activo por email (emitir uno nuevo borra los no usados), caducidad por defecto `INVITATION_EXPIRY_DAYS` (7 días), un solo uso.
- *Canje atómico*: `AuthController::createInvitedUser()` crea el usuario y canjea dentro de una transacción; `redeemFor()` usa `whereNull('used_at')->update()` para que ante registros simultáneos con el mismo código sólo gane uno. FK `used_by` con `cascadeOnDelete` ⇒ el borrado de cuenta GDPR arrastra el registro del canje.
- *Google*: iniciar sesión/vincular una cuenta EXISTENTE no pide invitación. Cuenta NUEVA vía Google exige `invitation_code` en el body de `/auth/google` (el campo del formulario de registro se envía junto al `id_token` desde `firebase-auth.js` vía `[data-invitation-code]`) y que el código case con el email **verificado** del token. Se eliminó el camino de cuentas con email sintético `@google.local` para emails no verificados: ahora se rechaza con 422 (sin email verificado no se puede casar la invitación).
- Tests: `tests/Feature/InvitationApiTest.php` y `AuthRegistrationTest` (actualizado).

## Decisiones Clave y Razones

1. **Verificación de Google con `firebase/php-jwt`, no con kreait Admin SDK**:
   - *Decisión*: El frontend (SDK JS de Firebase) hace el popup de Google y envía el ID
     token a `POST /auth/google`. El backend lo verifica en `App\Services\FirebaseTokenVerifier`
     decodificando el JWT (RS256) contra los certificados públicos de Google
     (`securetoken@system.gserviceaccount.com`, cacheados 1h) y validando `aud`/`iss`/`sub`
     frente a `FIREBASE_PROJECT_ID`.
   - *Justificación*: Sólo requiere el Project ID público, **no** una cuenta de servicio JSON.
     Más simple y seguro para el MVP. `kreait/laravel-firebase` está instalado pero NO se usa
     para esto (su Factory exige credenciales de servicio). Se mantiene como dependencia por si
     se necesita en el futuro.

2. **`users.password` ahora es nullable**:
   - Migración `2026_06_16_120000_add_auth_provider_fields_to_users_table` añade
     `firebase_uid` (unique), `avatar`, `accepted_privacy_at` y hace `password` nullable
     (los usuarios de Google no tienen contraseña local). Usa `->change()` nativo de Laravel 12.

3. **Vinculación de cuentas**: `AuthController@google` busca por `firebase_uid` o `email`; si
   existe un usuario con ese correo, le asocia el `firebase_uid` (forceFill). Tras autenticar,
   reusa la redirección común: onboarding si `onboarding_completed_at` es null, si no dashboard.

4. **Privacidad y Términos**: El registro tradicional exige marcar los checkboxes `privacy` y `terms` (`accepted`), guardando `accepted_privacy_at` y `accepted_terms_at`. El flujo de Google (Firebase) infiere la aceptación de ambos mediante disclaimers visuales en registro y login, persistiendo los mismos timestamps al crear/vincular el usuario.
   - Páginas estáticas: `route('privacy')` → `/privacidad` (`legal.privacy`) y `route('terms')` → `/terminos` (`legal.terms`, incluye el aviso legal de descargo de responsabilidad financiera).

5. **Exención del Banner de Consentimiento de Cookies (ePrivacy / GDPR)**:
   - *Decisión*: **No** se implementa banner de cookies ni consentimiento de rastreo.
   - *Justificación*: La plataforma utiliza exclusivamente cookies e identificadores técnicos estrictamente necesarios para la sesión (`laravel_session`), seguridad (`XSRF-TOKEN`) y autenticación (Firebase Auth Token local). Al no cargarse analíticas ni trackers de terceros (ej. Google Analytics, Facebook Pixel), están exentos de consentimiento. Un banner sería confuso y redundante para el usuario.

6. **Eliminación definitiva de cuenta y Derecho al Olvido (Art. 17 GDPR)**:
   - *Decisión*: Implementar una sección "Zona de Peligro" en el Panel de Control del Dashboard que permita la supresión de la cuenta del usuario de forma irreversible.
   - *Cierre preventivo de Binance:* Si el bot de futuros (apalancamiento 10x) está activo, se invoca automáticamente al broker `closeOpenPositions()` antes de eliminar los datos para evitar dejar posiciones reales expuestas sin control automatizado. Si la API de Binance falla, la eliminación local del usuario prosigue para garantizar su derecho legal de supresión de datos de carácter personal.
   - *Refactor 2026-07-03:* la lógica de cierre preventivo + borrado vive ahora en `App\Services\UserAccountDeleter`, compartida con la administración de usuarios (ver [admin-users.md](admin-users.md)); `AuthController@deleteAccount` sigue haciendo logout/invalidación de sesión antes de llamarla.
   - *Borrado en cascada:* Los snapshots de balance y el historial de actividades del bot se borran automáticamente mediante las claves foráneas en cascada en la base de datos sqlite.

## Evitar Bug de Re-inserción del Usuario al Eliminar (Laravel Remember Token)

- *Problema:* Si se llama a `$user->delete()` antes del cierre de sesión, `Auth::logout()` intenta limpiar el `remember_token` de la sesión activa modificando el modelo en memoria del usuario autenticado y llamando internamente a `$user->save()`. En Laravel, esto provoca que el usuario sea reinsertado de inmediato en la base de datos tras su eliminación.
- *Solución:* En `AuthController@deleteAccount()`, es mandatorio invalidar la sesión y realizar el logout **antes** de ejecutar `$user->delete()`:
  ```php
  Auth::logout();
  $request->session()->invalidate();
  $request->session()->regenerateToken();
  $user->delete();
  ```

## Configuración (no obvio)

- Env backend: `FIREBASE_PROJECT_ID`. Env frontend (expuestas al navegador):
  `VITE_FIREBASE_API_KEY`, `VITE_FIREBASE_AUTH_DOMAIN`, `VITE_FIREBASE_PROJECT_ID`,
  `VITE_FIREBASE_APP_ID`, `VITE_FIREBASE_MESSAGING_SENDER_ID`, `VITE_FIREBASE_STORAGE_BUCKET`.
  Si faltan, el botón de Google muestra "no configurado" pero el resto funciona.
- `resources/js/firebase-auth.js` es una **entrada de Vite aparte** (añadida en `vite.config.js`),
  cargada sólo en login/registro vía `@push('scripts') @vite(...)` desde
  `auth/partials/social.blade.php`. El layout expone `@stack('scripts')` y un meta `csrf-token`.
- Dependencias declaradas en composer.json (`firebase/php-jwt`, `kreait/laravel-firebase`) y
  package.json (`firebase`); ya estaban en vendor/node_modules pero sin declarar.
- Vistas movidas a `resources/views/auth/{login,register}.blade.php` (antes `login.blade.php`).
- Tests: `tests/Feature/AuthRegistrationTest.php` (mockea `FirebaseTokenVerifier`) y `tests/Feature/AccountDeletionTest.php` (valida el borrado en cascada y seguridad de Binance).

## Prevención del Bloqueo del Popup de Google (Firebase: auth/popup-blocked)

- *Problema:* El primer click en "Continuar con Google" resultaba en un error `Firebase: Error (auth/popup-blocked)`. Esto sucedía porque la inicialización de Firebase y la configuración de persistencia (`await setPersistence(...)`) eran ejecutadas asíncronamente *dentro* del manejador del evento click, interrumpiendo el flujo sincrónico que los navegadores exigen para permitir ventanas emergentes (popups).
- *Solución:* Mover la inicialización de Firebase (`initializeApp`), de Auth (`getAuth`) y de la persistencia al ámbito global (top-level scope) del script. Al cargarse la página, se inicializan de forma anticipada. De este modo, en el evento click se invoca directamente `signInWithPopup(auth, provider)` de forma sincrónica en el primer paso del manejador, lo cual es interpretado por el navegador como una acción de usuario válida y evita el bloqueo.


