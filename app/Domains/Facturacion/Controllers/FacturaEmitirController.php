<?php

namespace App\Domains\Facturacion\Controllers;



use App\Http\Controllers\Controller;
use App\Domains\Facturacion\Requests\EmitirFacturaRequest;
use App\Domains\Empresa\Models\Branch;
use App\Domains\Empresa\Models\PointOfSale;
use App\Domains\Facturacion\Services\Siat\Core\SiatServiceFactory;
use App\Domains\Facturacion\Services\Siat\Cufd\CufdService;
use App\Domains\Facturacion\Services\Siat\Facturacion\FacturaService;

class FacturaEmitirController extends Controller
{
    private FacturaService $facturaService;

    public function __construct(
        FacturaService $facturaService
    ) {
        $this->facturaService = $facturaService;
    }

    public function emitirFactura(EmitirFacturaRequest $request)
    {
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
                    'message' => 'Usuario no autenticado.'
                ], 401);
            }

            /*
            |--------------------------------------------------------------------------
            | 2. Empresa
            |--------------------------------------------------------------------------
            */

            $companyId = $user->id_company;

            if (!$companyId) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario no tiene una empresa asignada.'
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | 3. Datos validados
            |--------------------------------------------------------------------------
            */

            $data = $request->validated();

            $branchId =
                $data['identificacion_interna']['branch_id'];

            $puntoVentaId =
                $data['identificacion_interna']['punto_venta_id'];

            /*
            |--------------------------------------------------------------------------
            | 4. Validar sucursal
            |--------------------------------------------------------------------------
            */

            $branch = Branch::where('id', $branchId)
                ->where('id_company', $companyId)
                ->first();

            if (!$branch) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'La sucursal no existe o no pertenece a la empresa del usuario.'
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | 5. Validar punto de venta
            |--------------------------------------------------------------------------
            */

            $pointOfSale = PointOfSale::where('id', $puntoVentaId)
                ->where('id_company', $companyId)
                ->where('branch_id', $branchId)
                ->with('branch')
                ->first();

            if (!$pointOfSale) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'El punto de venta no existe o no pertenece a la empresa y sucursal seleccionadas.'
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | 6. Configuración SIAT
            |--------------------------------------------------------------------------
            */

            $siatConfig = SiatServiceFactory::forCompany(
                $companyId
            );

            /*
            |--------------------------------------------------------------------------
            | 7. Solicitar / obtener CUFD
            |--------------------------------------------------------------------------
            */

            $cufdService = new CufdService($siatConfig);

            $cufd = $cufdService->solicitar(
                $pointOfSale,
                
            );

          /*    return response()->json([
                'success' => true,
                'message' => 'cufd de . '.$pointOfSale->id,
                'data' => $cufd
            ]); */

            /*
            |--------------------------------------------------------------------------
            | 8. Procesar factura
            |--------------------------------------------------------------------------
            */

            $resultado = $this->facturaService->procesar(
                $data,
                $pointOfSale,//punto de venta de la sucursal
                $companyId,
                $branch
            );
            //aqui como hago para insert y toma el archiv xml e insetar en tabla


            /*
            |--------------------------------------------------------------------------
            | 9. RESPUESTA
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'success' => true,
                'message' => 'Factura procesada correctamente.',
                'data' => $resultado
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }
}