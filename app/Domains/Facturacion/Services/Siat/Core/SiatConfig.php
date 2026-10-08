<?php

namespace App\Domains\Facturacion\Services\Siat\Core;

use App\Domains\Facturacion\Models\SiatConfiguracion;

class SiatConfig
{
    /**
     * Parámetros generales de SIAT
     * para sincronización.
     */
    public static function sincronizacion(
        SiatConfiguracion $configuracion,
        int $codigoSucursal,
        int $codigoPuntoVenta,
        string $cuis
    ): array {

        return [

            'codigoAmbiente' =>
                (int)
                $configuracion->codigo_ambiente,

            'codigoSistema' =>
                (string)
                $configuracion->codigo_sistema,

            'nit' =>
                (int)
                $configuracion->nit,

            'codigoSucursal' =>
                $codigoSucursal,

            'codigoPuntoVenta' =>
                $codigoPuntoVenta,

            'cuis' =>
                $cuis,
        ];
    }
}