<?php

namespace App\Domains\Facturacion\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class EmitirFacturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            // ==========================================
            // IDENTIFICACIÓN INTERNA
            // ==========================================

            'identificacion_interna' => [
                'required',
                'array'
            ],

            'identificacion_interna.branch_id' => [
                'required'
            ],

            'identificacion_interna.punto_venta_id' => [
                'required'
            ],

            // ==========================================
            // CABECERA
            // ==========================================

            'cabecera' => [
                'required',
                'array'
            ],

            /* 'cabecera.codigoSucursal' => [
                'required',
                'integer'
            ], */

            'cabecera.actividadEconomica' => [
                'required',
                'string'
            ],

            'cabecera.codigoTipoDocumentoIdentidad' => [
                'required',
                'integer'
            ],

            'cabecera.codigoCliente' => [
                'required',
                'string'
            ],

            'cabecera.numeroDocumento' => [
                'required',
                'string'
            ],

            'cabecera.nombreRazonSocial' => [
                'required',
                'string'
            ],

            'cabecera.complemento' => [
                'nullable',
                'string'
            ],

            'cabecera.codigoMoneda' => [
                'required',
                'integer'
            ],

            'cabecera.tipoCambio' => [
                'required',
                'numeric'
            ],

            'cabecera.telefono' => [
                'nullable',
                'string'
            ],

            'cabecera.email' => [
                'nullable',
                'email'
            ],

            'cabecera.notaInterna' => [
                'nullable',
                'string'
            ],

            'cabecera.codigoMetodoPago' => [
                'required',
                'integer'
            ],

            'cabecera.montoTotal' => [
                'required',
                'numeric',
                'min:0'
            ],

            'cabecera.montoTotalSujetoIva' => [
                'required',
                'numeric',
                'min:0'
            ],

            'cabecera.montoTotalMoneda' => [
                'required',
                'numeric',
                'min:0'
            ],

            // ==========================================
            // DETALLE
            // ==========================================

            'detalle' => [
                'required',
                'array',
                'min:1'
            ],

            'detalle.*.actividadEconomica' => [
                'required',
                'string'
            ],

            'detalle.*.codigoProductoSin' => [
                'required',
                'integer'
            ],

            'detalle.*.codigoProducto' => [
                'required',
                'string'
            ],

            'detalle.*.descripcion' => [
                'required',
                'string'
            ],

            'detalle.*.cantidad' => [
                'required',
                'numeric',
                'gt:0'
            ],

            'detalle.*.unidadMedida' => [
                'required'
            ],

            'detalle.*.precioUnitario' => [
                'required',
                'numeric',
                'min:0'
            ],

            'detalle.*.montoDescuento' => [
                'nullable',
                'numeric',
                'min:0'
            ],

            'detalle.*.subTotal' => [
                'required',
                'numeric',
                'min:0'
            ],

            'detalle.*.numeroSerie' => [
                'nullable',
                'string'
            ],

            'detalle.*.numeroImei' => [
                'nullable',
                'string'
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'identificacion_interna.required'
            => 'La identificación interna es obligatoria.',

            'identificacion_interna.branch_id.required'
            => 'La sucursal es obligatoria.',

            'identificacion_interna.punto_venta_id.required'
            => 'El punto de venta es obligatorio.',

            'cabecera.required'
            => 'La cabecera de la factura es obligatoria.',

            'detalle.required'
            => 'La factura debe tener al menos un detalle.',

            'detalle.min'
            => 'La factura debe tener al menos un producto.',

            'detalle.*.cantidad.gt'
            => 'La cantidad debe ser mayor a cero.',

            'cabecera.email.email'
            => 'El correo electrónico no es válido.',
        ];
    }
}
