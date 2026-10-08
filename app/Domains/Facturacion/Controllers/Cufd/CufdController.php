<?php

namespace App\Domains\Facturacion\Controllers\Cufd;


use App\Http\Controllers\Controller;
use App\Domains\Empresa\Models\PointOfSale;
use App\Domains\Facturacion\Models\Cufd;
use App\Domains\Facturacion\Services\Siat\Core\SiatServiceFactory;
use App\Domains\Facturacion\Services\Siat\Cufd\CufdService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Throwable;

class CufdController extends Controller
{
    /**
     * =========================================================
     * LISTAR CUFD
     * =========================================================
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.'
            ], 401);
        }

        $query = Cufd::whereHas('pointOfSale', function ($query) use ($user) {
            $query->where('id_company', $user->id_company);
        });

        // Filtro opcional por punto de venta
        if ($request->filled('point_of_sale_id')) {
            $query->where(
                'punto_venta_id',
                $request->point_of_sale_id
            );
        }

        $cufd = $query
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $cufd
        ]);
    }


    /**
     * =========================================================
     * SOLICITAR CUFD
     * =========================================================
     *
     * POST:
     *
     * /api/facturacion/siat/cufd/solicitar
     *
     * BODY:
     *
     * {
     *     "sucursal_id": 1,
     *     "punto_venta_id": 1
     * }
     *
     */
    public function solicitar(Request $request): JsonResponse
    {
        try {

            // =====================================================
            // USUARIO
            // =====================================================

            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado.'
                ], 401);
            }


            // =====================================================
            // VALIDACIÓN
            // =====================================================

            $validator = Validator::make(
                $request->all(),
                [
                    'sucursal_id' => [
                        'required',
                        'integer'
                    ],

                    'punto_venta_id' => [
                        'required',
                        'integer'
                    ],
                ]
            );

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos inválidos.',
                    'errors' => $validator->errors()
                ], 422);
            }


            // =====================================================
            // EMPRESA
            // =====================================================
            $companyId = $user->id_company;

            if (!$companyId) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario no tiene una empresa asignada.'
                ], 422);
            }


            $sucursalId = $request->sucursal_id;
            $puntoVentaId = $request->punto_venta_id;

            // =====================================================
            // 4. BUSCAR PUNTO DE VENTA
            //
            // Debe pertenecer:
            //
            // - a la empresa del usuario
            // - a la sucursal enviada
            // - SOLO DEVUEL VE UN PUNTO UNO
            // =====================================================

            $pointOfSale = PointOfSale::with([
                'company',
                'branch'
            ])
                ->where('id', $puntoVentaId)
                ->where('id_company', $companyId)
                ->where('branch_id', $sucursalId)
                ->first();

            // =====================================================
            // 5. VALIDAR
            // =====================================================

            if (!$pointOfSale) {
                return response()->json([
                    'success' => false,
                    'message' =>
                    'El punto de venta no existe o no pertenece a la empresa y sucursal seleccionadas.'
                ], 422);
            }

            // =====================================================
            // 6. MOSTRAR INFORMACIÓN VALIDADA
            // =====================================================

           /*  return response()->json([
                'success' => true,
                'message' => 'Sucursal y punto de venta válidos.',

                'data' => [
                    'empresa' => [
                        'id_company' => $pointOfSale->company?->id_company,
                        'business_name' => $pointOfSale->company?->business_name,
                    ],

                    'sucursal' => [
                        'id' => $pointOfSale->branch?->id,
                        'codigo' => $pointOfSale->branch?->codigo,
                        'code_branch' => $pointOfSale->branch?->code_branch,
                        'branch_name' => $pointOfSale->branch?->branch_name,
                        'department' => $pointOfSale->branch?->department,
                        'municipality' => $pointOfSale->branch?->municipality,
                    ],

                    'punto_venta' => [
                        'id' => $pointOfSale->id,
                        'codigo' => $pointOfSale->codigo,
                        'name' => $pointOfSale->name,
                        'status' => $pointOfSale->status,
                        'branch_id' => $pointOfSale->branch_id,
                        'id_company' => $pointOfSale->id_company,
                    ],
                ]
            ], 200); */

            // =====================================================
            // VALIDAR SUCURSAL
            // =====================================================

            


            // =====================================================
            // SERVICIO SIAT
            // =====================================================

            $siat = SiatServiceFactory::forCompany(
                $user->id_company
            );

            $service = new CufdService($siat);


            // =====================================================
            // SOLICITAR CUFD
            // =====================================================

            /*
             * IMPORTANTE:
             *
             * Aquí enviamos el modelo PointOfSale.
             *
             * CufdService debe obtener desde:
             *
             *   $pointOfSale
             *
             * el código SIAT del punto de venta y,
             * mediante la sucursal relacionada,
             * el codigoSucursal.
             */

            $cufd = $service->solicitar(
                $pointOfSale
            );


            // =====================================================
            // RESPUESTA
            // =====================================================

            return response()->json([
                'success' => true,
                'message' => 'CUFD obtenido correctamente.',

                'data' => [
                    'id' => $cufd->id,

                    'codigo' => $cufd->codigo,

                    'codigo_control' => $cufd->codigo_control,

                    'fecha_inicio' => $cufd->fecha_inicio,

                    'fecha_vigencia' => $cufd->fecha_vigencia,

                    'vigente' => $cufd->vigente,

                    'sucursal_id' => $request->sucursal_id,

                    'punto_venta_id' => $cufd->punto_venta_id,
                ]
            ]);
        } catch (Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),

                // Útil mientras estás desarrollando
                'error' => get_class($e),
            ], 422);
        }
    }
}
