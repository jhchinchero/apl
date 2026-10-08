<?php
namespace App\Domains\Facturacion\Services\Siat\Anulacion;

use App\Domains\Empresa\Models\PointOfSale;
use App\Domains\Facturacion\Models\Cufd;
use App\Domains\Facturacion\Models\Cuis;
use App\Domains\Facturacion\Models\FacturaCabecera;
use App\Domains\Facturacion\Services\Siat\Core\SiatService;
use RuntimeException;

class FacturaAnulacionService
{
    public function __construct(
        private SiatService $siat
    ) {
    }

    /**
     * ============================================================
     * ANULAR FACTURA EN SIAT
     * ============================================================
     */
    public function anular(
        FacturaCabecera $factura,
        int $codigoMotivo
    ): array {

        /*
        |--------------------------------------------------------------------------
        | 1. CONFIGURACIÓN SIAT
        |--------------------------------------------------------------------------
        */

        $config = $this->siat->config();

        if (!$config->activo) {
            throw new RuntimeException(
                'La configuración SIAT de la empresa está inactiva.'
            );
        }

        if (!$config->token_delegado) {
            throw new RuntimeException(
                'La empresa no tiene Token Delegado configurado.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 2. VALIDAR FACTURA
        |--------------------------------------------------------------------------
        */

        if (empty($factura->cuf)) {
            throw new RuntimeException(
                'La factura no tiene CUF.'
            );
        }

        if ($factura->estado != 1) {
            throw new RuntimeException(
                'La factura no se encuentra en estado válido para ser anulada.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 3. OBTENER PUNTO DE VENTA
        |--------------------------------------------------------------------------
        */

        $pointOfSale = $factura->puntoVenta;

        if (!$pointOfSale) {
            throw new RuntimeException(
                'La factura no tiene un punto de venta asociado.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 4. OBTENER SUCURSAL
        |--------------------------------------------------------------------------
        */

        $branch = $pointOfSale->branch;

        if (!$branch) {
            throw new RuntimeException(
                'El punto de venta no tiene una sucursal asociada.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 5. CÓDIGOS SIAT
        |--------------------------------------------------------------------------
        |
        | IMPORTANTE:
        | Aquí utilizamos los códigos SIAT, NO los IDs internos.
        |
        */

        $codigoSucursal = (int) $branch->codigo;

        $codigoPuntoVenta = (int) $pointOfSale->codigo;


        /*
        |--------------------------------------------------------------------------
        | 6. BUSCAR CUIS VIGENTE
        |--------------------------------------------------------------------------
        */

        $cuis = Cuis::where(
            'punto_venta_id',
            $pointOfSale->id
        )
        ->where('vigente', true)
        ->where(
            'fecha_vigencia',
            '>',
            now()
        )
        ->latest('id')
        ->first();

        if (!$cuis) {
            throw new RuntimeException(
                'El punto de venta no tiene un CUIS vigente.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 7. BUSCAR CUFD
        |--------------------------------------------------------------------------
        |
        | Para la anulación necesitamos el CUFD asociado al
        | punto de venta.
        |
        */

        $cufd = Cufd::where(
            'punto_venta_id',
            $pointOfSale->id
        )
        ->where('vigente', true)
        ->latest('id')
        ->first();

        if (!$cufd) {
            throw new RuntimeException(
                'El punto de venta no tiene un CUFD registrado.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 8. VALIDAR CUFD
        |--------------------------------------------------------------------------
        */

        if (
            $cufd->fecha_vigencia &&
            $cufd->fecha_vigencia < now()
        ) {
            throw new RuntimeException(
                'El CUFD del punto de venta se encuentra vencido.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 9. WSDL
        |--------------------------------------------------------------------------
        */

        $wsdl = config('siat.wsdl.facturacion');

        if (!$wsdl) {
            throw new RuntimeException(
                'No está configurado el WSDL del servicio de facturación SIAT.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 10. CLIENTE SOAP
        |--------------------------------------------------------------------------
        */

        $soap = $this->siat->soap($wsdl);


        /*
        |--------------------------------------------------------------------------
        | 11. PREPARAR SOLICITUD
        |--------------------------------------------------------------------------
        |
        | SIAT:
        |
        | AnulacionFactura
        |
        */

        $params = [

            'SolicitudServicioAnulacionFactura' => [

                'codigoAmbiente' =>
                    (int) $config->codigo_ambiente,

                'codigoPuntoVenta' =>
                    $codigoPuntoVenta,

                'codigoSistema' =>
                    $config->codigo_sistema,

                'codigoSucursal' =>
                    $codigoSucursal,

                'nit' =>
                    $config->nit,

                'codigoDocumentoSector' =>
                    (int) $factura->codigo_documento_sector,

                'codigoEmision' =>
                    (int) $config->codigo_emision,

                'codigoModalidad' =>
                    (int) $config->codigo_modalidad,

                'cufd' =>
                    $cufd->codigo,

                'cuis' =>
                    $cuis->codigo,

                'tipoFacturaDocumento' =>
                    (int) $config->tipo_factura_documento,

                'codigoMotivo' =>
                    $codigoMotivo,

                'cuf' =>
                    $factura->cuf,
            ],
        ];


        /*
        |--------------------------------------------------------------------------
        | 12. LLAMAR A SIAT
        |--------------------------------------------------------------------------
        */

        $respuesta = $soap->call(
            'AnulacionFactura',
            $params
        );

             // dd($respuesta);


       /*
|--------------------------------------------------------------------------
| 13. VALIDAR RESPUESTA
|--------------------------------------------------------------------------
*/

if (!isset($respuesta->RespuestaServicioFacturacion)) {

    throw new RuntimeException(
        'SIAT no devolvió una respuesta válida para la anulación.'
    );
}


$data = $respuesta->RespuestaServicioFacturacion;


/*
|--------------------------------------------------------------------------
| 14. OBTENER DATOS DE SIAT
|--------------------------------------------------------------------------
*/

$transaccion =
    (bool) ($data->transaccion ?? false);

$codigoEstado =
    $data->codigoEstado ?? null;

$codigoDescripcion =
    $data->codigoDescripcion ?? null;


/*
|--------------------------------------------------------------------------
| 15. OBTENER MENSAJE SIAT
|--------------------------------------------------------------------------
*/

$mensajeCodigo = null;

$mensajeDescripcion = null;

if (isset($data->mensajesList)) {

    $mensajeCodigo =
        $data->mensajesList->codigo
        ?? null;

    $mensajeDescripcion =
        $data->mensajesList->descripcion
        ?? null;
}


/*
|--------------------------------------------------------------------------
| 16. SIAT RECHAZÓ LA ANULACIÓN
|--------------------------------------------------------------------------
*/

if (!$transaccion) {

    return [

        'transaccion' => false,

        'codigo_estado' =>
            $codigoEstado,

        'codigo_descripcion' =>
            $codigoDescripcion,

        'mensaje' => [
            'codigo' =>
                $mensajeCodigo,

            'descripcion' =>
                $mensajeDescripcion,
        ],

        'cuf' =>
            $factura->cuf,

        'numero_factura' =>
            $factura->numero_factura,
    ];
}


/*
|--------------------------------------------------------------------------
| 17. ANULACIÓN CONFIRMADA
|--------------------------------------------------------------------------
*/

return [

    'transaccion' => true,

    'codigo_estado' =>
        $codigoEstado,

    'codigo_descripcion' =>
        $codigoDescripcion,

    'mensaje' => [
        'codigo' =>
            $mensajeCodigo,

        'descripcion' =>
            $mensajeDescripcion,
    ],

    'cuf' =>
        $factura->cuf,

    'numero_factura' =>
        $factura->numero_factura,
];
    }
}