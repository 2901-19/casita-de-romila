<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cart' => 'required|array',
            'cart.*.product_id' => [
                'required',
                function ($attribute, $value, $fail) {
                    if (! is_numeric($value) && ! preg_match('/^combo_\d+$/', $value)) {
                        $fail('El ID del producto debe ser un número o un ID de combo válido.');
                    }
                },
            ],
            'cart.*.name' => 'required|string|max:150',
            'cart.*.price' => 'required|numeric|min:0',
            'cart.*.quantity' => 'required|integer|min:1',
            'payment_method' => 'required|in:efectivo,biopago,pago_movil,pdv,credito',
            'customer_id' => [
                'nullable',
                'required_if:payment_method,credito',
                Rule::exists('customers', 'id')->where('is_active', true),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'cart.required' => 'El carrito está vacío.',
            'cart.min' => 'El carrito debe tener al menos un producto.',
            'payment_method.required' => 'Seleccione un método de pago.',
            'payment_method.in' => 'Método de pago inválido.',
            'customer_id.required_if' => 'Seleccione el cliente para la venta a crédito.',
            'customer_id.exists' => 'El cliente seleccionado no es válido.',
        ];
    }
}
