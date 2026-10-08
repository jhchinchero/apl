<?php



namespace App\Domains\Facturacion\Services\Siat\Cufd;

use App\Domains\Empresa\Models\PointOfSale;
use App\Domains\Facturacion\Models\Cufd;
use App\Domains\Facturacion\Models\Cuis;

use App\Domains\Facturacion\Services\Siat\Core\SiatService;

use Carbon\Carbon;
use RuntimeException;

class CufdService
{
    public function __construct(
        private SiatService $siat
    ) {
    }

    /**
     * ============================================================
     * SOLICITAR CUFD
     * ============================================================
     *
     * Si existe un CUFD vigente para el punto de venta,
     * se devuelve ese mismo CUFD y NO se solicita otro a SIAT.
     *
     * Si no existe o ya venció, se solicita uno nuevo.
     */
    public function solicitar(PointOfSale $pointOfSale): Cufd
    {
        /*
        |--------------------------------------------------------------------------
        | 1. Buscar CUFD vigente existente
        |--------------------------------------------------------------------------
        */

        $cufdExistente = Cufd::where(
            'punto_venta_id',
            $pointOfSale->id
        )
        ->where('vigente', true)
        ->latest('id')
        ->first();


        /*
        |--------------------------------------------------------------------------
        | 2. Si existe, comprobar fecha de vigencia
        |--------------------------------------------------------------------------
        */

        if ($cufdExistente) {

            /*
            |--------------------------------------------------------------------------
            | CUFD todavía vigente
            |--------------------------------------------------------------------------
            */

            if (
                $cufdExistente->fecha_vigencia
                && Carbon::parse(
                    $cufdExistente->fecha_vigencia
                )->isFuture()
            ) {

                /*
                |--------------------------------------------------------------------------
                | NO llamar a SIAT
                |--------------------------------------------------------------------------
                */

                return $cufdExistente;
            }


            /*
            |--------------------------------------------------------------------------
            | CUFD vencido
            |--------------------------------------------------------------------------
            */

            $cufdExistente->update([
                'vigente' => false,
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | 3. Configuración SIAT
        |--------------------------------------------------------------------------
        */

        $config = $this->siat->config();

        if (!$config->activo) {

            throw new RuntimeException(
                'La configuración SIAT de la empresa está inactiva.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 4. Validar Token Delegado
        |--------------------------------------------------------------------------
        */

        if (!$config->token_delegado) {

            throw new RuntimeException(
                'La empresa no tiene Token Delegado configurado.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 5. Sucursal
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
        | 6. CUIS vigente
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
        | 7. Códigos SIAT
        |--------------------------------------------------------------------------
        */

        $codigoSucursal = (int) $branch->codigo;

        $codigoPuntoVenta = (int) $pointOfSale->codigo;


        /*
        |--------------------------------------------------------------------------
        | 8. WSDL CUFD
        |--------------------------------------------------------------------------
        */

        $wsdl = config('siat.wsdl.codigos');

        if (!$wsdl) {

            throw new RuntimeException(
                'No está configurado el WSDL del servicio CUFD.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 9. Cliente SOAP
        |--------------------------------------------------------------------------
        */

        $soap = $this->siat->soap($wsdl);


        /*
        |--------------------------------------------------------------------------
        | 10. Solicitud CUFD
        |--------------------------------------------------------------------------
        */

        $params = [

            'SolicitudCufd' => [

                'codigoAmbiente' =>
                    $config->codigo_ambiente,

                'codigoSistema' =>
                    $config->codigo_sistema,

                'nit' =>
                    $config->nit,

                'codigoModalidad' =>
                    $config->codigo_modalidad,

                'codigoSucursal' =>
                    $codigoSucursal,

                'codigoPuntoVenta' =>
                    $codigoPuntoVenta,

                'cuis' =>
                    $cuis->codigo,
            ],
        ];


        /*
        |--------------------------------------------------------------------------
        | 11. Solicitar CUFD a SIAT
        |--------------------------------------------------------------------------
        */

        $respuesta = $soap->call(
            'cufd',
            $params
        );


        /*
        |--------------------------------------------------------------------------
        | 12. Validar respuesta
        |--------------------------------------------------------------------------
        */

        if (!isset($respuesta->RespuestaCufd)) {

            throw new RuntimeException(
                'SIAT no devolvió una respuesta válida para CUFD.'
            );
        }


        $data = $respuesta->RespuestaCufd;


        /*
        |--------------------------------------------------------------------------
        | 13. Validar transacción
        |--------------------------------------------------------------------------
        */

        if (
            isset($data->transaccion)
            && !$data->transaccion
        ) {

            $mensaje = 'SIAT rechazó la solicitud de CUFD.';

            if (
                isset($data->mensajesList)
            ) {

                if (
                    is_object($data->mensajesList)
                ) {

                    $mensajeSiat =
                        $data->mensajesList->descripcion
                        ?? null;

                    if ($mensajeSiat) {

                        $mensaje .= ' ' . $mensajeSiat;
                    }
                }
            }

            throw new RuntimeException(
                $mensaje
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 14. Validar código CUFD
        |--------------------------------------------------------------------------
        */

        if (empty($data->codigo)) {

            throw new RuntimeException(
                'SIAT no devolvió el código CUFD.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 15. Validar código de control
        |--------------------------------------------------------------------------
        */

        if (empty($data->codigoControl)) {

            throw new RuntimeException(
                'SIAT no devolvió el código de control del CUFD.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 16. Validar fecha de vigencia
        |--------------------------------------------------------------------------
        */

        if (empty($data->fechaVigencia)) {

            throw new RuntimeException(
                'SIAT no devolvió la fecha de vigencia del CUFD.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 17. Convertir fecha
        |--------------------------------------------------------------------------
        */

        $fechaVigencia = Carbon::parse(
            $data->fechaVigencia
        );


        /*
        |--------------------------------------------------------------------------
        | 18. Desactivar CUFD anteriores
        |--------------------------------------------------------------------------
        */

        Cufd::where(
            'punto_venta_id',
            $pointOfSale->id
        )
        ->where('vigente', true)
        ->update([
            'vigente' => false,
        ]);


        /*
        |--------------------------------------------------------------------------
        | 19. Guardar nuevo CUFD
        |--------------------------------------------------------------------------
        */

        return Cufd::create([

            'codigo' =>
                $data->codigo,

            'codigo_control' =>
                $data->codigoControl,

            'fecha_inicio' =>
                now(),

            'fecha_vigencia' =>
                $fechaVigencia,

            'vigente' =>
                true,

            'punto_venta_id' =>
                $pointOfSale->id,
        ]);
    }
}
