<?php



namespace App\Domains\Facturacion\Services\Siat\Cuis;



use App\Domains\Empresa\Models\PointOfSale;
use App\Domains\Facturacion\Models\Cuis;
use App\Domains\Facturacion\Services\Siat\Core\SiatService;
use Carbon\Carbon;
use RuntimeException;

class CuisService
{
    public function __construct(
        private SiatService $siat
    ) {}

    /**
     * Solicita un nuevo CUIS para un punto de venta.
     */
    public function solicitar(PointOfSale $pointOfSale): Cuis
    {
        /*
        |--------------------------------------------------------------------------
        | 1. Validar configuración SIAT
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
        | 2. Obtener sucursal
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
        | 3. Obtener códigos
        |--------------------------------------------------------------------------
        */

        $codigoSucursal = (int) $branch->codigo;

        $codigoPuntoVenta = (int) $pointOfSale->codigo;







        /*
        |--------------------------------------------------------------------------
        | 4. WSDL
        |--------------------------------------------------------------------------
        */

        $wsdl =config('siat.wsdl.codigos');

        if (!$wsdl) {
            throw new RuntimeException(
                'No está configurado el WSDL del servicio CUIS.'
            );
        }






        /*
        |--------------------------------------------------------------------------
        | 5. Crear cliente SIAT
        |--------------------------------------------------------------------------
        */

        $soap = $this->siat->soap($wsdl);

        /*
        |--------------------------------------------------------------------------
        | 6. Parámetros solicitados por SIAT
        |--------------------------------------------------------------------------
        
        */



        $params = [
            'SolicitudCuis' => [
                'codigoAmbiente' => 2, //$config->codigo_ambiente,

                'codigoSistema' => $config->codigo_sistema,

                'nit' => $config->nit,

                'codigoModalidad' => 1, // $config->codigo_modalidad,

                'codigoSucursal' => $codigoSucursal,

                'codigoPuntoVenta' => $codigoPuntoVenta

            ],
        ];

        /*
        |--------------------------------------------------------------------------
        | 7. Solicitar CUIS
        |--------------------------------------------------------------------------
        */

        $respuesta = $soap->call(
            'cuis',
            $params
        );




        /*
        |--------------------------------------------------------------------------
        | 8. Validar respuesta
        |--------------------------------------------------------------------------
        */


        if (
            !isset($respuesta->RespuestaCuis)
        ) {
            throw new RuntimeException(
                'SIAT no devolvió una respuesta válida para CUIS.'
            );
        }

        $data = $respuesta->RespuestaCuis;

        /*
|--------------------------------------------------------------------------
| 9. Obtener datos de respuesta SIAT
|--------------------------------------------------------------------------
*/

        $codigoCuis = $data->codigo ?? null;
        $fechaVigencia = $data->fechaVigencia ?? null;

        $mensajeCodigo = null;
        $mensajeDescripcion = null;

        if (isset($data->mensajesList)) {

            // Caso normal: objeto
            if (is_object($data->mensajesList)) {

                $mensajeCodigo = $data->mensajesList->codigo ?? null;

                $mensajeDescripcion =
                    $data->mensajesList->descripcion ?? null;
            }

            // Por si SIAT devuelve array
            elseif (is_array($data->mensajesList)) {

                $mensajeCodigo =
                    $data->mensajesList['codigo'] ?? null;

                $mensajeDescripcion =
                    $data->mensajesList['descripcion'] ?? null;
            }
        }


        /*
|--------------------------------------------------------------------------
| 10. CASO 980
|--------------------------------------------------------------------------
|
| SIAT informa:
|
| EXISTE UN CUIS VIGENTE PARA LA SUCURSAL O PUNTO DE VENTA
|
| En este caso NO debemos crear otro CUIS.
|
|--------------------------------------------------------------------------
*/

        if (
            isset($mensajeCodigo)
            && (int) $mensajeCodigo === 980
        ) {

            /*
    |--------------------------------------------------------------------------
    | Buscar CUIS vigente que ya tenemos registrado localmente
    |--------------------------------------------------------------------------
    */

            $cuisExistente = Cuis::where(
                'punto_venta_id',
                $pointOfSale->id
            )
                ->where('vigente', true)
                ->first();


            /*
    |--------------------------------------------------------------------------
    | Si ya existe en nuestra BD
    |--------------------------------------------------------------------------
    */

            if ($cuisExistente) {

                return $cuisExistente;
            }


            /*
    |--------------------------------------------------------------------------
    | Si SIAT devuelve el código CUIS pero nosotros no lo tenemos
    |--------------------------------------------------------------------------
    */

            if (
                !empty($codigoCuis)
                && !empty($fechaVigencia)
            ) {

                return Cuis::create([

                    'codigo' => $codigoCuis,

                    'fecha_solicitada' => now(),

                    'fecha_vigencia' => Carbon::parse(
                        $fechaVigencia
                    ),

                    'vigente' => true,

                    'punto_venta_id' => $pointOfSale->id,
                ]);
            }


            /*
    |--------------------------------------------------------------------------
    | SIAT dice que existe pero no devuelve código
    |--------------------------------------------------------------------------
    */

            throw new RuntimeException(
                'SIAT informa que existe un CUIS vigente para la sucursal o punto de venta, pero no devolvió el código CUIS. ' .
                    ($mensajeDescripcion ?? '')
            );
        }


        /*
|--------------------------------------------------------------------------
| 11. Validar transacción normal
|--------------------------------------------------------------------------
*/

        if (
            isset($data->transaccion)
            && !$data->transaccion
        ) {

            $mensaje = 'SIAT rechazó la solicitud de CUIS.';

            if (!empty($mensajeDescripcion)) {

                $mensaje .= ' ' . $mensajeDescripcion;
            }

            throw new RuntimeException($mensaje);
        }


        /*
|--------------------------------------------------------------------------
| 12. Validar código CUIS
|--------------------------------------------------------------------------
|
| IMPORTANTE:
| SIAT devuelve:
|
| codigo
|
| NO:
|
| codigoCUIS
|
|--------------------------------------------------------------------------
*/

        if (empty($codigoCuis)) {

            throw new RuntimeException(
                'SIAT no devolvió el código CUIS.'
            );
        }


        /*
|--------------------------------------------------------------------------
| 13. Validar fecha de vigencia
|--------------------------------------------------------------------------
*/

        if (empty($fechaVigencia)) {

            throw new RuntimeException(
                'SIAT no devolvió la fecha de vigencia del CUIS.'
            );
        }


        /*
|--------------------------------------------------------------------------
| 14. Desactivar CUIS anterior
|--------------------------------------------------------------------------
*/

        Cuis::where(
            'punto_venta_id',
            $pointOfSale->id
        )
            ->where('vigente', true)
            ->update([
                'vigente' => false,
            ]);


        /*
|--------------------------------------------------------------------------
| 15. Guardar nuevo CUIS
|--------------------------------------------------------------------------
*/

        return Cuis::create([

            'codigo' => $codigoCuis,

            'fecha_solicitada' => now(),

            'fecha_vigencia' => Carbon::parse(
                $fechaVigencia
            ),

            'vigente' => true,

            'punto_venta_id' => $pointOfSale->id,
        ]);
    }
}
