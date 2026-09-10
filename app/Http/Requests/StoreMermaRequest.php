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
            'type' => ['required', Rule::in(['merma', 'consumo'])],
            'reason' => ['required', Rule::in($this->input('type') === 'consumo'
                ? ['otro']
                : ['vencido', 'danado', 'otro'])],
            'notes' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
            'lines.*.cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'lines.required' => 'Debe agregar al menos un producto.',
            'lines.min' => 'Debe agregar al menos un producto.',
            'type.required' => 'Seleccione el tipo de salida.',
            'reason.required' => 'Seleccione una razón.',
        ];
    }
}