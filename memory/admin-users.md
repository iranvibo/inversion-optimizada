---
created: 2026-07-03
updated: 2026-07-27
---

# Administración de usuarios

## Autorización de administrador

- No hay columna `is_admin` en BD: el admin se define por email en `config('app.admin_email')` (env `ADMIN_EMAIL`, default `vicenteiran@gmail.com`). Decisión deliberada para el MVP: un solo admin, sin migración, y el email puede cambiarse por entorno sin tocar código.
- `User::isAdmin()` compara con `strcasecmp` (case-insensitive) y devuelve `false` si la config está vacía.
- Gate `admin` definido en `AppServiceProvider::boot()`. Se usa como middleware de rutas (`can:admin`) y en Blade (`@can('admin')`).

## Pestaña "Usuarios" del dashboard

- Segundo tab (tras "Panel de Control"), solo se renderiza para el admin; el título incluye el total: `Usuarios (N)`. `N` inicial lo pasa `DashboardController::index` (`$registeredUsersCount`, `null` para no-admins: no se consulta si no se ve).
- Contenido cargado por AJAX al abrir el tab (mismo patrón que la pestaña Actividad / `refreshActivities`): `GET /admin/users` (JSON), `DELETE /admin/users/{user}` y `POST /admin/invitations`. Controlador: `AdminUserController`.
- El JSON expone SOLO campos no sensibles vía un map explícito (nunca credenciales cifradas ni password). Ojo: el controlador hace `get()` sin select limitado porque `isBrokerLinked()` necesita las columnas de credenciales/verificación — no re-"optimizar" con select parcial.
- **Generación de Enlaces de Invitación**: Botón y modal `#admin-invite-modal` para emitir un código y URL directa (`/register?invitation=CODE`) para un correo concreto mediante `POST /admin/invitations` (`storeInvitation`). Verifica que no exista el usuario (409 Conflict) y permite copiar la URL al portapapeles con un clic.
- El front escapa nombre/email con `escapeHtml()` antes de `innerHTML` (son datos introducidos por el usuario → XSS almacenado).
- El admin no puede borrarse a sí mismo desde ese panel (422); para eso está la eliminación GDPR de cuenta propia.
- En los tests de visibilidad de la pestaña, asertar sobre `id="tab-btn-users"` (con escape desactivado), no sobre `tab-btn-users` a secas: el JS del dashboard referencia ese id para todos los usuarios.

## Eliminación de cuentas compartida

- `App\Services\UserAccountDeleter::delete(User)` centraliza el borrado definitivo: si el bot está activo cierra posiciones abiertas en cada canal vinculado (Binance/Hyperliquid, fallos independientes que no bloquean el borrado, GDPR) y luego `delete()` (cascada en BD).
- Lo usan `AuthController::deleteAccount` (auto-eliminación GDPR; hace logout/invalidate ANTES de borrar para no tocar `remember_token` de un usuario eliminado) y `AdminUserController::destroy`.

Tests: `tests/Feature/AdminUsersTest.php`.
