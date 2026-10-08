<?php

namespace App\Domains\Facturacion\Controllers;





use App\Domains\Empresa\Models\PointOfSale;
use App\Domains\Facturacion\Models\SiatConfiguracion;
use App\Domains\Facturacion\Services\Siat\Core\SiatService;
use App\Http\Controllers\Controller;

use App\Domains\Facturacion\Services\Siat\Core\SiatServiceFactory;
use App\Domains\Facturacion\Services\Siat\Core\TestService;
use App\Domains\Facturacion\Services\Siat\Cuis\CuisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Domains\Facturacion\Models\Cuis;
use Throwable;

class CuisController extends Controller
{


    /*
    |--------------------------------------------------------------------------
    | LISTAR CUIS DE LA EMPRESA
    |--------------------------------------------------------------------------
    */

    public function index(
        Request $request
    ): JsonResponse {

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $cuis = Cuis::whereHas(
            'pointOfSale',
            function ($query) use ($user) {

                $query->where(
                    'id_company',
                    $user->id_company
                );

            }
        )
        ->orderByDesc('id')
        ->get();

        return response()->json([
            'success' => true,
            'data' => $cuis,
        ]);
    }

    public function test(): JsonResponse
    {
        try {


            $user = request()->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado.',
                ], 401);
            }

            /**
             * Obtener servicio SIAT de la empresa
             */
            $siat = SiatServiceFactory::forCompany(
                $user->id_company
            );



            /**
             * Obtener configuración
             */
            $config = $siat->config();

            return response()->json([
                'success' => true,

                'message' => 'Configuración SIAT cargada correctamente.',

                'data' => $config,
            ], 200);
        } catch (Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
   
     /*
    |--------------------------------------------------------------------------
    | SOLICITAR CUIS
    |--------------------------------------------------------------------------
    */


public function solicitar(Request $request): JsonResponse
{
    try {

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.'
            ], 401);
        }

        $request->validate([
            'sucursal_id' => ['required', 'integer'],
            'punto_venta_id' => ['required', 'integer'],
        ]);

     

        $pointOfSale = PointOfSale::with('branch')
            ->where('id', $request->punto_venta_id)
            ->where('id_company', $user->id_company)
            ->firstOrFail();

          

        /*
         * Aquí el backend obtiene:
         *
         * pointOfSale->branch
         * pointOfSale->codigo...
         *
         * y CuisService convierte los IDs internos
         * en códigos SIAT.
         */

        $siat = SiatServiceFactory::forCompany(
            $user->id_company
        );

        $service = new CuisService($siat);

        $cuis = $service->solicitar(
            $pointOfSale
        );

        return response()->json([
            'success' => true,
            'message' => 'CUIS obtenido correctamente.',
            'data' => [
                'id' => $cuis->id,
                'codigo' => $cuis->codigo,
                'fecha_solicitada' => $cuis->fecha_solicitada,
                'fecha_vigencia' => $cuis->fecha_vigencia,
                'vigente' => $cuis->vigente,
                'punto_venta_id' => $cuis->punto_venta_id,
            ],
        ]);

    } catch (\Throwable $e) {

        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 422);
    }
}

}


/*
5. Ruta
Route::post(
    '/siat/cuis/{pointOfSale}/solicitar',
    [CuisController::class, 'solicitar']
);

Entonces desde Vue:

POST /api/siat/cuis/1/solicitar

y el sistema sabe:

POS 1
 ↓
branch_id
 ↓
branches.codigo
 ↓
point_of_sales.codigo
 ↓
empresa
 ↓
siat_configuraciones
 ↓
token
 ↓
SIAT
*/