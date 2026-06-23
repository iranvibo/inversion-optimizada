<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Core\Contracts\SignalProviderInterface;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'firebase_uid',
        'avatar',
        'accepted_privacy_at',
        'accepted_terms_at',
        'binance_api_key',
        'binance_secret_key',
        'binance_verified',
        'bot_active',
        'bot_mode',
        'risk_level',
        'binance_withdrawal_alert',
        'estimated_capital',
        'onboarding_completed_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'current_position',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'accepted_privacy_at' => 'datetime',
            'accepted_terms_at' => 'datetime',
            'password' => 'hashed',
            'binance_api_key' => 'encrypted',
            'binance_secret_key' => 'encrypted',
            'binance_verified' => 'boolean',
            'bot_active' => 'boolean',
            'binance_withdrawal_alert' => 'boolean',
            'estimated_capital' => 'decimal:2',
            'onboarding_completed_at' => 'datetime',
        ];
    }

    /**
     * Serie temporal de snapshots del balance consolidado (US03).
     */
    public function balanceSnapshots(): HasMany
    {
        return $this->hasMany(BalanceSnapshot::class);
    }

    /**
     * Historial de actividad del bot (US05).
     */
    public function botActivities(): HasMany
    {
        return $this->hasMany(BotActivity::class);
    }

    /**
     * Determina si el usuario ya completó el onboarding inicial.
     */
    public function hasCompletedOnboarding(): bool
    {
        return $this->onboarding_completed_at !== null;
    }

    /**
     * Determina si el usuario tiene una cuenta de Binance vinculada y verificada.
     */
    public function isBinanceLinked(): bool
    {
        return $this->binance_verified && ! empty($this->binance_api_key) && ! empty($this->binance_secret_key);
    }

    /**
     * Determina si el bot está activo.
     */
    public function hasActiveBot(): bool
    {
        return $this->bot_active;
    }

    /**
     * Accesor para obtener la posición actual del bot (Simulación vs Real).
     */
    public function getCurrentPositionAttribute(): string
    {
        if (! $this->bot_active) {
            return 'CLOSE';
        }

        if ($this->bot_mode === 'simulation') {
            $riskLevel = strtolower($this->risk_level);
            $apiPosition = Cache::get("signal:last_known_position:{$riskLevel}");
            if (! $apiPosition) {
                try {
                    $signalProvider = app(SignalProviderInterface::class);
                    $signal = $signalProvider->getCurrentSignal($riskLevel);
                    $apiPosition = strtoupper($signal['position'] ?? 'CLOSE');
                    Cache::put("signal:last_known_position:{$riskLevel}", $apiPosition);
                } catch (\Exception $e) {
                    $apiPosition = 'CLOSE';
                }
            }

            return $apiPosition;
        }

        return Cache::get("user:{$this->id}:real_position", 'CLOSE');
    }
}
