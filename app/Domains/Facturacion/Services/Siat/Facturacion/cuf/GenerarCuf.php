<?php

namespace App\Domains\Facturacion\Services\Siat\Facturacion\Cuf;

use Carbon\Carbon;
use Exception;

class GenerarCuf
{
    /**
     * Genera el CUF de una factura electrónica SIAT.
     *
     * IMPORTANTE:
     * - $sucursalCodigo debe ser el código SIAT de la sucursal.
     * - $puntoVentaCodigo debe ser el código SIAT del punto de venta.
     * - $fecha debe ser EXACTAMENTE la misma fecha/hora enviada en el XML.
     */
    public function generarCuf(
        $siatConfig,
        $cufd,
        $sucursalCodigo,
        $puntoVentaCodigo,
        $numeroFactura,
        $fecha = null
    ) {
        
        /*
        |--------------------------------------------------------------------------
        | 1. DATOS SIAT
        |--------------------------------------------------------------------------
        */

        $nit = trim((string) $siatConfig->nit);

        $modalidad = (string) $siatConfig->codigo_modalidad;

        $tipoEmision = (string) $siatConfig->codigo_emision;

        $tipoFactura = (string) $siatConfig->tipo_factura_documento;

        $tipoSector = (string) $siatConfig->codigo_documento_sector;

        /*
        |--------------------------------------------------------------------------
        | 2. SUCURSAL
        |--------------------------------------------------------------------------
        |
        | Debe ser el código de sucursal SIAT.
        |
        | Ejemplo:
        |
        | sucursal = 0
        | sucursal = 1
        | sucursal = 2
        |
        */

        $sucursalCodigo = (string) $sucursalCodigo;

        /*
        |--------------------------------------------------------------------------
        | 3. PUNTO DE VENTA
        |--------------------------------------------------------------------------
        |
        | IMPORTANTE:
        |
        | NO usar:
        |
        | $cufd->punto_venta_id
        |
        | si ese campo es el ID interno de tu tabla.
        |
        | Debemos utilizar el código SIAT del punto de venta.
        |
        */

        $puntoVentaCodigo = (string) $puntoVentaCodigo;

        /*
        |--------------------------------------------------------------------------
        | 4. NÚMERO DE FACTURA
        |--------------------------------------------------------------------------
        */

        $numeroFactura = (string) $numeroFactura;

        /*
        |--------------------------------------------------------------------------
        | 5. FECHA
        |--------------------------------------------------------------------------
        |
        | Debe ser exactamente la misma fecha utilizada en el XML.
        |
        */

        if ($fecha === null) {
            $fecha = Carbon::now('America/La_Paz');
        } else {
            $fecha = $fecha instanceof Carbon
                ? $fecha->copy()->setTimezone('America/La_Paz')
                : Carbon::parse($fecha, 'America/La_Paz');
        }

        /*
        |--------------------------------------------------------------------------
        | FORMATO SIAT
        |--------------------------------------------------------------------------
        |
        | YYYYMMDDHHMMSSmmm
        |
        | 17 dígitos.
        |
        */

        $fechaCuf = $fecha->format('YmdHisv');

        /*
        |--------------------------------------------------------------------------
        | 6. CÓDIGO DE CONTROL DEL CUFD
        |--------------------------------------------------------------------------
        */

        $codigoControl = strtoupper(
            trim((string) $cufd->codigo_control)
        );

        if ($codigoControl === '') {
            throw new Exception(
                'El CUFD no tiene código de control.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 7. CONSTRUCCIÓN DE LA CADENA
        |--------------------------------------------------------------------------
        */

        $cadena =
            str_pad($nit, 13, '0', STR_PAD_LEFT) .
            $fechaCuf .
            str_pad($sucursalCodigo, 4, '0', STR_PAD_LEFT) .
            $modalidad .
            $tipoEmision .
            $tipoFactura .
            str_pad($tipoSector, 2, '0', STR_PAD_LEFT) .
            str_pad($numeroFactura, 10, '0', STR_PAD_LEFT) .
            str_pad($puntoVentaCodigo, 4, '0', STR_PAD_LEFT);

        /*
        |--------------------------------------------------------------------------
        | 8. VALIDAR QUE SOLO EXISTAN DÍGITOS
        |--------------------------------------------------------------------------
        */

        if (!ctype_digit($cadena)) {
            throw new Exception(
                'La cadena para generar el CUF contiene caracteres no numéricos: ' .
                $cadena
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 9. MODULO 11
        |--------------------------------------------------------------------------
        */

        $digitoVerificador = $this->modulo11($cadena);

        /*
        |--------------------------------------------------------------------------
        | 10. AGREGAR DÍGITO VERIFICADOR
        |--------------------------------------------------------------------------
        */

        $numero = $cadena . $digitoVerificador;

        /*
        |--------------------------------------------------------------------------
        | 11. CONVERTIR DECIMAL → HEXADECIMAL
        |--------------------------------------------------------------------------
        */

        if (!extension_loaded('gmp')) {
            throw new Exception(
                'La extensión GMP no está habilitada en PHP.'
            );
        }

        $hexadecimal = gmp_strval(
            gmp_init($numero, 10),
            16
        );

        /*
        |--------------------------------------------------------------------------
        | 12. CUF FINAL
        |--------------------------------------------------------------------------
        */

        return strtoupper(
            $hexadecimal . $codigoControl
        );
    }


    /**
     * Algoritmo Módulo 11 utilizado para el CUF.
     */
    private function modulo11(string $cadena): int
    {
        $suma = 0;

        $factor = 2;

        for ($i = strlen($cadena) - 1; $i >= 0; $i--) {

            $suma += ((int) $cadena[$i]) * $factor;

            $factor++;

            if ($factor > 9) {
                $factor = 2;
            }
        }

        $modulo = 11 - ($suma % 11);

        if ($modulo === 10) {
            return 1;
        }

        if ($modulo === 11) {
            return 0;
        }

        return $modulo;
    }
}