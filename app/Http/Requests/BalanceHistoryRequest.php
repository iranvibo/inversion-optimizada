<?php

namespace App\Http\Requests;

use App\Core\Portfolio\TimeRange;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validación del filtro temporal del gráfico de evolución (US03, Escenario 2).
 * Capa más externa: sanea la entrada antes de tocar el caso de uso.
 */
class BalanceHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La ruta ya está protegida por el middleware 'auth'.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'range' => ['sometimes', 'string', Rule::enum(TimeRange::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'range' => 'El filtro temporal debe ser uno de: día, semana o mes.',
        ];
    }

    /**
     * Rango temporal validado, con "Día" como vista por defecto.
     */
    public function range(): TimeRange
    {
        $validated = $this->validated('range');

        return $validated === null ? TimeRange::Month : TimeRange::from($validated);
    }
}
