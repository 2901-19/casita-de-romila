<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExchangeRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rate' => ['required', 'numeric', 'min:0.01'],
            'source' => ['required', 'in:bcv,paralelo,binance,enzona,manual'],
        ];
    }

    public function messages(): array
    {
        return [
            'rate.required' => 'La tasa es obligatoria.',
            'rate.numeric' => 'La tasa debe ser un número.',
            'rate.min' => 'La tasa debe ser mayor a 0.',
            'source.required' => 'La fuente es obligatoria.',
            'source.in' => 'Fuente inválida.',
        ];
    }
}
