<?php

namespace App\Domains\Facturacion\Controllers;


use App\Domains\Empresa\Models\PointOfSale;
use App\Domains\Facturacion\Services\Siat\Core\SiatService;
use App\Domains\Facturacion\Services\Siat\Cuis\CuisService;
use App\Domains\Facturacion\Services\Siat\Cufd\CufdService;
use App\Domains\Facturacion\Models\SiatConfiguracion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class SiatController
{
    /**
     * Solicitar CUIS para un punto de venta.
     */
    public function solicitarCUIS(
        Request $request,
        PointOfSale $pointOfSale
    ): JsonResponse {

        try {

            /*
            |--------------------------------------------------------------------------
            | 1. Usuario autenticado
            |--------------------------------------------------------------------------
            */

            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado.',
                ], 401);
            }

            /*
            |--------------------------------------------------------------------------
            | 2. Verificar que el punto de venta pertenece
            |    a la empresa del usuario
            |--------------------------------------------------------------------------
            */

            if (
                $pointOfSale->id_company !== $user->id_company
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'El punto de venta no pertenece a la empresa actual.',
                ], 403);
            }

            /*
            |--------------------------------------------------------------------------
            | 3. Obtener configuración SIAT
            |--------------------------------------------------------------------------
            */

            $configuracion = SiatConfiguracion::where(
                'id_company',
                $user->id_company
            )
            ->where('activo', true)
            ->first();

            if (!$configuracion) {
                return response()->json([
                    'success' => false,
                    'message' => 'La empresa no tiene una configuración SIAT activa.',
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | 4. Crear SiatService
            |--------------------------------------------------------------------------
            */

            $siat = new SiatService(
                $configuracion
            );

            /*
            |--------------------------------------------------------------------------
            | 5. Crear CuisService
            |--------------------------------------------------------------------------
            */

            $cuisService = new CuisService(
                $siat
            );

            /*
            |--------------------------------------------------------------------------
            | 6. Solicitar CUIS
            |--------------------------------------------------------------------------
            */

            $cuis = $cuisService->solicitar(
                $pointOfSale
            );

            /*
            |--------------------------------------------------------------------------
            | 7. Respuesta
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'success' => true,
                'message' => 'CUIS solicitado correctamente.',
                'data' => [
                    'id' => $cuis->id,
                    'codigo' => $cuis->codigo,
                    'fecha_solicitada' => $cuis->fecha_solicitada,
                    'fecha_vigencia' => $cuis->fecha_vigencia,
                    'vigente' => $cuis->vigente,
                    'punto_venta_id' => $cuis->punto_venta_id,
                ],
            ]);

        } catch (Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }


    /**
     * Solicitar CUFD para un punto de venta.
     */
    public function solicitarCUFD(
        Request $request,
        PointOfSale $pointOfSale
    ): JsonResponse {

        try {

            /*
            |--------------------------------------------------------------------------
            | 1. Usuario autenticado
            |--------------------------------------------------------------------------
            */

            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado.',
                ], 401);
            }

            /*
            |--------------------------------------------------------------------------
            | 2. Verificar empresa
            |--------------------------------------------------------------------------
            */

            if (
                $pointOfSale->id_company !== $user->id_company
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'El punto de venta no pertenece a la empresa actual.',
                ], 403);
            }

            /*
            |--------------------------------------------------------------------------
            | 3. Obtener configuración SIAT
            |--------------------------------------------------------------------------
            */

            $configuracion = SiatConfiguracion::where(
                'id_company',
                $user->id_company
            )
            ->where('activo', true)
            ->first();

            if (!$configuracion) {
                return response()->json([
                    'success' => false,
                    'message' => 'La empresa no tiene una configuración SIAT activa.',
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | 4. Crear SiatService
            |--------------------------------------------------------------------------
            */

            $siat = new SiatService(
                $configuracion
            );

            /*
            |--------------------------------------------------------------------------
            | 5. Crear CufdService
            |--------------------------------------------------------------------------
            */

            $cufdService = new CufdService(
                $siat
            );

            /*
            |--------------------------------------------------------------------------
            | 6. Solicitar CUFD
            |--------------------------------------------------------------------------
            */

            $cufd = $cufdService->solicitar(
                $pointOfSale
            );

            /*
            |--------------------------------------------------------------------------
            | 7. Respuesta
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'success' => true,
                'message' => 'CUFD solicitado correctamente.',
                'data' => [
                    'id' => $cufd->id,
                    'codigo' => $cufd->codigo,
                    'codigo_control' => $cufd->codigo_control,
                    'fecha_inicio' => $cufd->fecha_inicio,
                    'fecha_vigencia' => $cufd->fecha_vigencia,
                    'vigente' => $cufd->vigente,
                    'punto_venta_id' => $cufd->punto_venta_id,
                ],
            ]);

        } catch (Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}



/*
use App\Domains\Facturacion\Http\Controllers\SiatController;

Route::middleware('auth:sanctum')->prefix('siat')->group(function () {

    Route::post(
        '/puntos-venta/{pointOfSale}/cuis',
        [SiatController::class, 'solicitarCUIS']
    );

    Route::post(
        '/puntos-venta/{pointOfSale}/cufd',
        [SiatController::class, 'solicitarCUFD']
    );

});

POST /api/siat/puntos-venta/1/cuis
POST /api/siat/puntos-venta/1/cufd











3. ¿Qué pasa cuando llamas CUIS?

Por ejemplo:

POST /api/siat/puntos-venta/1/cuis

El Controller hace:

$user = $request->user();

Supongamos:

id_company = EMP-00001

Entonces busca:

$configuracion = SiatConfiguracion::where(
    'id_company',
    'EMP-00001'
)->where('activo', true)->first();

Obtiene:

siat_configuraciones

EMP-00001
NIT             123456789
codigo_sistema  MI_SISTEMA
ambiente        2
modalidad       1
token_delegado  XXXXXXX

Después:

$siat = new SiatService($configuracion);

Y:

$cuisService = new CuisService($siat);

Finalmente:

$cuis = $cuisService->solicitar($pointOfSale);

Y dentro de CuisService ocurre:

$wsdl = config('siat.wsdl.codigos');

que obtiene:

SIAT_CODIGOS_WSDL

del .env.

4. ¿Y CUFD?

Exactamente igual:

POST /api/siat/puntos-venta/1/cufd

Pero entra aquí:

$cufdService = new CufdService($siat);

$cufd = $cufdService->solicitar($pointOfSale);

Y CufdService busca primero el CUIS vigente:

$cuis = Cuis::where(
    'punto_venta_id',
    $pointOfSale->id
)
->where('vigente', true)
->latest('id')
->first();

Por eso el orden correcto es:

1. Configurar empresa SIAT
          ↓
2. Solicitar CUIS
          ↓
3. Guardar CUIS
          ↓
4. Solicitar CUFD
          ↓
5. Guardar CUFD
          ↓
6. Emitir facturas
Un detalle importante

No necesitas enviar desde Vue:

{
    "nit": "...",
    "token": "...",
    "codigoSistema": "..."
}

Nada de eso.

Desde Vue solamente mandas el pointOfSale:

await axios.post(`/api/siat/puntos-venta/${pointOfSale.id}/cuis`);

o:

await axios.post(`/api/siat/puntos-venta/${pointOfSale.id}/cufd`);

El backend determina automáticamente:

Usuario autenticado
      ↓
id_company
      ↓
configuración SIAT
      ↓
token
      ↓
punto de venta
      ↓
sucursal
      ↓
CUIS/CUFD

Eso es mucho más seguro, porque el frontend nunca controla el id_company, NIT ni Token Delegado.
*/