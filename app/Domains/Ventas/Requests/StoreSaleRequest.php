<?php

namespace App\Domains\Ventas\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'accion' => ['required', 'string', 'in:venta'],

            'client_id' => [
                'required',
                'integer',
                'exists:clients,id'
            ],

            'branch_id' => [
                'required',
                'integer',
                'exists:branches,id'
            ],

            'items' => [
                'required',
                'array',
                'min:1'
            ],

            'items.*.product_id' => [
                'required',
                'integer',
                'exists:products,id'
            ],

            'items.*.quantity' => [
                'required',
                'numeric',
                'gt:0'
            ],

            'items.*.price' => [
                'required',
                'numeric',
                'gte:0'
            ],

            'subtotal' => [
                'required',
                'numeric',
                'gte:0'
            ],

            'discount' => [
                'nullable',
                'numeric',
                'gte:0'
            ],

            'total' => [
                'required',
                'numeric',
                'gte:0'
            ],

            'usedBalance' => [
                'nullable',
                'numeric',
                'gte:0'
            ],

            'payments' => [
                'nullable',
                'array'
            ],

            'payments.*.payment_method' => [
                'required',
                'string',
                'in:CASH,CARD,TRANSFER,QR,CHECK,BALANCE,OTHER'
            ],

            'payments.*.amount' => [
                'required',
                'numeric',
                'gt:0'
            ],

            'payments.*.reference' => [
                'nullable',
                'string',
                'max:255'
            ],

            'payments.*.observation' => [
                'nullable',
                'string'
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'accion.in' => 'La acción debe ser venta.',

            'client_id.exists' =>
                'El cliente seleccionado no existe.',

            'branch_id.exists' =>
                'La sucursal seleccionada no existe.',

            'items.required' =>
                'Debe registrar al menos un producto.',

            'items.min' =>
                'Debe registrar al menos un producto.',

            'items.*.product_id.exists' =>
                'Uno de los productos no existe.',

            'items.*.quantity.gt' =>
                'La cantidad debe ser mayor a cero.',

            'items.*.price.gte' =>
                'El precio no puede ser negativo.',
        ];
    }
}