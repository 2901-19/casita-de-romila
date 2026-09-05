<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreComboRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'round_bs' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'products' => ['required', 'array', 'min:1'],
            'products.*.id' => ['required', 'exists:products,id'],
            'products.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del combo es obligatorio.',
            'sale_price.required' => 'El precio de venta es obligatorio.',
            'products.required' => 'Debe agregar al menos un producto al combo.',
            'products.min' => 'Debe agregar al menos un producto al combo.',
        ];
    }
}
