<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Core\Portfolio\BotActivityFormatter;

/**
 * Modelo para las actividades y transacciones del bot (US05).
 */
class BotActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'bot_mode',
        'type',
        'action',
        'description',
        'profit_percentage',
        'profit_value',
        'risk_alert',
        'raw_details',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'profit_percentage' => 'decimal:2',
            'profit_value' => 'decimal:2',
            'risk_alert' => 'boolean',
            'raw_details' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted(): void
    {
        static::creating(function ($activity) {
            if (empty($activity->bot_mode)) {
                $user = $activity->user ?: User::find($activity->user_id);
                $activity->bot_mode = $user ? $user->bot_mode : 'simulation';
            }
        });
    }

    /**
     * Atributo dinámico para obtener la descripción en lenguaje humano.
     */
    public function getHumanDescriptionAttribute(): string
    {
        return BotActivityFormatter::format($this);
    }
}
