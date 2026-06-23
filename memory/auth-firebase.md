---
created: 2026-06-16
updated: 2026-06-23
---

# Autenticación: contraseña, Google (Firebase) y privacidad

Ampliación del flujo de login/registro de ViBo Invest (US01): registro self-service con
contraseña, inicio de sesión con Google vía Firebase y consentimiento de política de
privacidad.

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
- Tests: `tests/Feature/AuthRegistrationTest.php` (mockea `FirebaseTokenVerifier`).
