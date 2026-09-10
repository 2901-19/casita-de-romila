<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMermaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'type' => ['required', Rule::in(['merma', 'consumo'])],
            'reason' => ['required', Rule::in($this->input('type') === 'consumo'
                ? ['otro']
                : ['vencido', 'danado', 'otro'])],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Seleccione un producto.',
            'quantity.min' => 'La cantidad debe ser al menos 1.',
            'type.required' => 'Seleccione el tipo de salida.',
            'reason.required' => 'Seleccione una razón.',
        ];
    }
}
