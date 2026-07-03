<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Código de invitación de un solo uso, emitido para un email concreto.
 *
 * El código en claro nunca se persiste: se guarda su SHA-256 (code_hash) y el
 * valor legible sólo viaja una vez en la respuesta de la API de invitaciones.
 */
class InvitationCode extends Model
{
    /**
     * Alfabeto sin caracteres ambiguos (0/O, 1/I/L) para códigos legibles por
     * humanos. 32 símbolos × 16 posiciones = 80 bits de entropía.
     */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    private const CODE_LENGTH = 16;

    protected $fillable = [
        'email',
        'code_hash',
        'expires_at',
        'used_at',
        'used_by',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    /**
     * Usuario que canjeó el código.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by');
    }

    /**
     * Emite un código nuevo para un email, invalidando cualquier código previo
     * sin usar de ese mismo email (sólo hay una invitación activa por persona).
     *
     * @return array{invitation: self, code: string} El código en claro sólo existe aquí.
     */
    public static function issueFor(string $email, ?int $expiryDays = null): array
    {
        $email = Str::lower(trim($email));

        static::query()
            ->where('email', $email)
            ->whereNull('used_at')
            ->delete();

        $plain = static::generateCode();

        $invitation = static::create([
            'email' => $email,
            'code_hash' => static::hashCode($plain),
            'expires_at' => now()->addDays($expiryDays ?? (int) config('invitations.expiry_days')),
        ]);

        return ['invitation' => $invitation, 'code' => $plain];
    }

    /**
     * Busca un código canjeable (sin usar y no caducado) que corresponda al
     * par email + código. La búsqueda por hash evita comparar en texto claro.
     */
    public static function findRedeemable(string $email, string $plainCode): ?self
    {
        return static::query()
            ->where('email', Str::lower(trim($email)))
            ->where('code_hash', static::hashCode($plainCode))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Marca el código como canjeado por un usuario. La condición whereNull
     * hace el canje atómico: ante dos registros simultáneos con el mismo
     * código, sólo uno consigue actualizar la fila.
     */
    public function redeemFor(User $user): bool
    {
        return static::query()
            ->whereKey($this->id)
            ->whereNull('used_at')
            ->update(['used_at' => now(), 'used_by' => $user->id]) === 1;
    }

    /**
     * Genera un código aleatorio criptográficamente seguro, agrupado en
     * bloques de 4 para facilitar su lectura (XXXX-XXXX-XXXX-XXXX).
     */
    private static function generateCode(): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $chars = '';
        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $chars .= self::ALPHABET[random_int(0, $max)];
        }

        return implode('-', str_split($chars, 4));
    }

    /**
     * Normaliza (mayúsculas, sin separadores) y hashea el código. La
     * normalización perdona guiones/espacios y minúsculas al teclearlo.
     */
    private static function hashCode(string $plainCode): string
    {
        $normalized = strtoupper((string) preg_replace('/[\s-]+/', '', $plainCode));

        return hash('sha256', $normalized);
    }
}
