<?php

namespace App\Domains\facturacion\Services;

use Exception;
use Illuminate\Support\Facades\DB;

use App\Domains\Facturacion\Models\FacturaCabecera;

class FacturaDbService
{
    /**
     * Guarda una factura completa desde el XML.
     *
     * XML
     *  ├── cabecera → factura_cabecera
     *  └── detalle  → factura_detalle
     *
     * @param string $xmlContenido
     * @param int $puntoVentaId
     * @return FacturaCabecera
     *
     * @throws Exception
     */
    public function guardarDesdeXml(
        string $xmlContenido,
        string $companyId,
        int $puntoVentaId
    ): FacturaCabecera {

        /*
        |--------------------------------------------------------------------------
        | 1. INTERPRETAR XML
        |--------------------------------------------------------------------------
        */

        libxml_use_internal_errors(true);

        $xml = simplexml_load_string($xmlContenido);

        if ($xml === false) {

            $errores = libxml_get_errors();

            libxml_clear_errors();

            $mensaje = 'No se pudo interpretar el XML de la factura.';

            if (!empty($errores)) {
                $mensaje .= ' ' . trim($errores[0]->message);
            }

            throw new Exception($mensaje);
        }

        /*
        |--------------------------------------------------------------------------
        | 2. VALIDAR CABECERA
        |--------------------------------------------------------------------------
        */

        if (!isset($xml->cabecera)) {
            throw new Exception(
                'El XML no contiene la sección <cabecera>.'
            );
        }

        $cabecera = $xml->cabecera;

        /*
        |--------------------------------------------------------------------------
        | 3. TRANSACCIÓN
        |--------------------------------------------------------------------------
        */

        return DB::transaction(function () use (
            $cabecera,
            $xml,
            $puntoVentaId,
            $companyId
        ) {

            /*
            |--------------------------------------------------------------------------
            | FACTURA CABECERA
            |--------------------------------------------------------------------------
            */

            $factura = FacturaCabecera::create([
                'id_company' => $companyId,

                'estado' => 1,

                'nit_emisor' => $this->stringValue(
                    $cabecera->nitEmisor
                ),

                'razon_social_emisor' => $this->stringValue(
                    $cabecera->razonSocialEmisor
                ),

                'municipio' => $this->stringValue(
                    $cabecera->municipio
                ),

                'telefono' => $this->stringOrNull(
                    $cabecera->telefono
                ),

                'numero_factura' => $this->integerValue(
                    $cabecera->numeroFactura
                ),

                'cuf' => $this->stringValue(
                    $cabecera->cuf
                ),

                'cufd' => $this->stringValue(
                    $cabecera->cufd
                ),

                'codigo_sucursal' => $this->stringValue(
                    $cabecera->codigoSucursal
                ),

                'direccion' => $this->stringValue(
                    $cabecera->direccion
                ),

                'codigo_punto_venta' => $this->stringValue(
                    $cabecera->codigoPuntoVenta
                ),

                'fecha_emision' => $this->stringValue(
                    $cabecera->fechaEmision
                ),

                'nombre_razon_social' => $this->stringValue(
                    $cabecera->nombreRazonSocial
                ),

                'codigo_tipo_documento_identidad' => $this->integerValue(
                    $cabecera->codigoTipoDocumentoIdentidad
                ),

                'numero_documento' => $this->stringValue(
                    $cabecera->numeroDocumento
                ),

                'complemento' => $this->stringOrNull(
                    $cabecera->complemento
                ),

                'codigo_cliente' => $this->stringValue(
                    $cabecera->codigoCliente
                ),

                'codigo_metodo_pago' => $this->integerValue(
                    $cabecera->codigoMetodoPago
                ),

                'numero_tarjeta' => $this->stringOrNull(
                    $cabecera->numeroTarjeta
                ),

                'monto_total' => $this->decimalValue(
                    $cabecera->montoTotal
                ),

                'monto_total_sujeto_iva' => $this->decimalValue(
                    $cabecera->montoTotalSujetoIva
                ),

                'codigo_moneda' => $this->integerValue(
                    $cabecera->codigoMoneda
                ),

                'tipo_cambio' => $this->decimalValue(
                    $cabecera->tipoCambio
                ),

                'monto_total_moneda' => $this->decimalValue(
                    $cabecera->montoTotalMoneda
                ),

                'monto_gift_card' => $this->decimalOrNull(
                    $cabecera->montoGiftCard
                ),

                'descuento_adicional' => $this->decimalOrNull(
                    $cabecera->descuentoAdicional
                ),

                'codigo_excepcion' => $this->integerOrNull(
                    $cabecera->codigoExcepcion
                ),

                'cafc' => $this->stringOrNull(
                    $cabecera->cafc
                ),

                'leyenda' => $this->stringValue(
                    $cabecera->leyenda
                ),

                'usuario' => $this->stringValue(
                    $cabecera->usuario
                ),

                'codigo_documento_sector' => $this->integerValue(
                    $cabecera->codigoDocumentoSector
                ),

                /*
                |--------------------------------------------------------------------------
                | RELACIÓN CON PUNTO DE VENTA
                |--------------------------------------------------------------------------
                */

                'punto_venta_id' => $puntoVentaId,
            ]);

            /*
            |--------------------------------------------------------------------------
            | FACTURA DETALLE
            |--------------------------------------------------------------------------
            */

            if (!isset($xml->detalle)) {
                throw new Exception(
                    'El XML no contiene ningún detalle de factura.'
                );
            }

            foreach ($xml->detalle as $detalle) {

                $factura->detalles()->create([

                    'actividad_economica' => $this->stringValue(
                        $detalle->actividadEconomica
                    ),

                    'codigo_producto_sin' => $this->integerValue(
                        $detalle->codigoProductoSin
                    ),

                    'codigo_producto' => $this->stringValue(
                        $detalle->codigoProducto
                    ),

                    'descripcion' => $this->stringValue(
                        $detalle->descripcion
                    ),

                    'cantidad' => $this->decimalValue(
                        $detalle->cantidad
                    ),

                    'unidad_medida' => $this->stringValue(
                        $detalle->unidadMedida
                    ),

                    'precio_unitario' => $this->decimalValue(
                        $detalle->precioUnitario
                    ),

                    'monto_descuento' => $this->decimalOrNull(
                        $detalle->montoDescuento
                    ),

                    'sub_total' => $this->decimalValue(
                        $detalle->subTotal
                    ),

                    'numero_serie' => $this->stringOrNull(
                        $detalle->numeroSerie
                    ),

                    'numero_imei' => $this->stringOrNull(
                        $detalle->numeroImei
                    ),
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | RETORNAR FACTURA
            |--------------------------------------------------------------------------
            */

            return $factura->load('detalles');
        });
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    private function stringValue($value): string
    {
        return trim((string) $value);
    }

    private function stringOrNull($value): ?string
    {
        $value = trim((string) $value);

        return $value === ''
            ? null
            : $value;
    }

    private function integerValue($value): int
    {
        return (int) $value;
    }

    private function integerOrNull($value): ?int
    {
        $value = trim((string) $value);

        return $value === ''
            ? null
            : (int) $value;
    }

    private function decimalValue($value): float
    {
        return (float) $value;
    }

    private function decimalOrNull($value): ?float
    {
        $value = trim((string) $value);

        return $value === ''
            ? null
            : (float) $value;
    }
}
