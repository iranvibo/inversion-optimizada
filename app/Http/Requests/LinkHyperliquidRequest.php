<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LinkHyperliquidRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * En mock se aceptan credenciales de prueba laxas (palabras clave tipo
     * 'invalid'/'master'); en real se exige el formato estricto de una
     * dirección Ethereum y de una clave privada hexadecimal.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if (config('services.hyperliquid.mock')) {
            return [
                'hyperliquid_wallet_address' => ['required', 'string', 'min:5', 'max:255'],
                'hyperliquid_agent_key' => ['required', 'string', 'min:5', 'max:255'],
            ];
        }

        return [
            'hyperliquid_wallet_address' => ['required', 'string', 'regex:/^0x[0-9a-fA-F]{40}$/'],
            'hyperliquid_agent_key' => ['required', 'string', 'regex:/^(0x)?[0-9a-fA-F]{64}$/'],
        ];
    }

    /**
     * Get customized error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'hyperliquid_wallet_address.required' => 'La dirección de tu wallet de Hyperliquid es obligatoria.',
            'hyperliquid_wallet_address.regex' => 'La dirección debe tener el formato 0x seguido de 40 caracteres hexadecimales.',
            'hyperliquid_agent_key.required' => 'La clave privada de la API wallet es obligatoria.',
            'hyperliquid_agent_key.regex' => 'La clave privada debe tener 64 caracteres hexadecimales (con o sin el prefijo 0x).',
        ];
    }
}
