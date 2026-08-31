<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:20'],
            'credit_limit_type' => ['nullable', 'in:libre,monto'],
            'credit_limit_amount' => ['nullable', 'numeric', 'min:0', 'required_if:credit_limit_type,monto'],
        ];
    }

    public function messages(): array
    {
        return [
            'credit_limit_amount.required_if' => 'Indique el monto del límite de crédito.',
        ];
    }
}
