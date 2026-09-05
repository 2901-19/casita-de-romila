<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'control_type' => ['required', 'in:inventariable,demanda,produccion'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'margin_percent' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'price_override' => ['nullable', 'boolean'],
            'round_bs' => ['nullable', 'integer', 'min:1'],
            'stock_min' => ['nullable', 'integer', 'min:0'],
            'stock_current' => ['nullable', 'integer', 'min:0'],
            'schedule' => ['required', 'in:manana,finde_noche,ambos'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'control_type.required' => 'El tipo de control es obligatorio.',
            'schedule.required' => 'El horario es obligatorio.',
        ];
    }
}
