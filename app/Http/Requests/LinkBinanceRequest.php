<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LinkBinanceRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'binance_api_key' => ['required', 'string', 'min:10', 'max:255'],
            'binance_secret_key' => ['required', 'string', 'min:10', 'max:255'],
        ];
    }

    /**
     * Get customized error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'binance_api_key.required' => 'La API Key de Binance es obligatoria.',
            'binance_api_key.min' => 'La API Key debe tener al menos 10 caracteres.',
            'binance_secret_key.required' => 'La Secret Key de Binance es obligatoria.',
            'binance_secret_key.min' => 'La Secret Key debe tener al menos 10 caracteres.',
        ];
    }
}
