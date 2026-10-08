<?php

namespace App\Domains\Facturacion\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Domains\Facturacion\Models\FacturaCabecera;


use App\Domains\Facturacion\Services\Siat\Core\SiatServiceFactory;
use App\Domains\Facturacion\Services\Siat\Anulacion\FacturaAnulacionService;
use Illuminate\Validation\ValidationException;

class FacturaController extends Controller
{
    /**
     * =========================================================
     * LISTAR FACTURAS
     * =========================================================
     */
    public function listar(Request $request)
    {
        $user = $request->user();

        $query = FacturaCabecera::query()
            ->where('id_company', $user->id_company)
            ->with('puntoVenta')
            ->orderByDesc('fecha_emision');

        // =====================================================
        // BUSCAR
        // =====================================================

        if ($request->filled('search')) {

            $search = trim($request->search);

            $query->where(function ($q) use ($search) {

                $q->where('numero_documento', 'like', "%{$search}%")
                    ->orWhere('nombre_razon_social', 'like', "%{$search}%")
                    ->orWhere('numero_factura', 'like', "%{$search}%")
                    ->orWhere('cuf', 'like', "%{$search}%");
            });
        }

        // =====================================================
        // FECHA INICIO
        // =====================================================

        if ($request->filled('fecha_inicio')) {

            $query->whereDate(
                'fecha_emision',
                '>=',
                $request->fecha_inicio
            );
        }

        // =====================================================
        // FECHA FIN
        // =====================================================

        if ($request->filled('fecha_fin')) {

            $query->whereDate(
                'fecha_emision',
                '<=',
                $request->fecha_fin
            );
        }

        // =====================================================
        // PAGINACIÓN
        // =====================================================

        return response()->json(
            $query->paginate(
                $request->integer('per_page', 5)
            )
        );
    }

    /**
     * =========================================================
     * VER FACTURA
     * =========================================================
     */
    public function obtener(Request $request, int $id)
    {
        $factura = FacturaCabecera::where(
            'id_company',
            $request->user()->id_company
        )
            ->with('detalles')
            ->findOrFail($id);

        return response()->json($factura);
    }

    public function anular(Request $request, int $id)
    {
        /*
    |--------------------------------------------------------------------------
    | 1. VALIDAR MOTIVO
    |--------------------------------------------------------------------------
    */

        $validated = $request->validate([
            'codigo_motivo' => [
                'required',
                'integer',
            ],
        ]);


        /*
    |--------------------------------------------------------------------------
    | 2. USUARIO AUTENTICADO
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
    | 3. BUSCAR FACTURA
    |--------------------------------------------------------------------------
    |
    | IMPORTANTE:
    | La factura se busca utilizando también id_company.
    | Así un usuario no puede anular una factura de otra empresa.
    |
    */

        $factura = FacturaCabecera::where(
            'id_company',
            $user->id_company
        )
            ->with([
                'puntoVenta.branch',
            ])
            ->find($id);


        if (!$factura) {
            return response()->json([
                'success' => false,
                'message' => 'Factura no encontrada.',
            ], 404);
        }


        /*
    |--------------------------------------------------------------------------
    | 4. VALIDAR ESTADO
    |--------------------------------------------------------------------------
    |
    | Estado 1 = factura válida/emitida
    | Estado 2 = factura anulada
    |
    */

        if ((int) $factura->estado !== 1) {

            return response()->json([
                'success' => false,
                'message' =>
                'La factura no puede ser anulada porque no se encuentra en estado válido.',
            ], 422);
        }


        /*
    |--------------------------------------------------------------------------
    | 5. VALIDAR PUNTO DE VENTA
    |--------------------------------------------------------------------------
    */

        if (!$factura->puntoVenta) {

            return response()->json([
                'success' => false,
                'message' =>
                'La factura no tiene un punto de venta asociado.',
            ], 422);
        }


        /*
    |--------------------------------------------------------------------------
    | 6. CREAR SERVICIO SIAT
    |--------------------------------------------------------------------------
    */

        try {

            $siat = SiatServiceFactory::forCompany(
                $user->id_company
            );


            /*
        |--------------------------------------------------------------------------
        | 7. CREAR SERVICIO DE ANULACIÓN
        |--------------------------------------------------------------------------
        */

            $servicio = new FacturaAnulacionService(
                $siat
            );


            /*
        |--------------------------------------------------------------------------
        | 8. ANULAR EN SIAT
        |--------------------------------------------------------------------------
        |
        | AQUÍ ocurre realmente la comunicación con SIAT.
        |
        */

            $resultado = $servicio->anular(
                $factura,
                (int) $validated['codigo_motivo']
            );


            /*
|--------------------------------------------------------------------------
| SIAT RECHAZÓ
|--------------------------------------------------------------------------
*/

            if (!$resultado['transaccion']) {

                return response()->json([
                    'success' => false,

                    'message' =>
                    $resultado['codigo_descripcion']
                        ?? 'SIAT rechazó la anulación.',

                    'data' => $resultado,

                ], 422);
            }


            /*
|--------------------------------------------------------------------------
| SIAT CONFIRMÓ
|--------------------------------------------------------------------------
*/

            $factura->update([
                'estado' => 2,
            ]);

            try {
                $factura->refresh();

                $pdf = app(\App\Domains\Facturacion\Services\FacturaPdfService::class)
                    ->generar($factura, null, true);

                $factura->update([
                    'pdf_path' => $pdf['path'],
                ]);
            } catch (\Throwable $e) {
                report($e);
            }


            return response()->json([

                'success' => true,

                'message' =>
                $resultado['codigo_descripcion']
                    ?? 'ANULACION CONFIRMADA',

                'data' => $resultado,

            ], 200);
        } catch (\Throwable $e) {

            /*
        |--------------------------------------------------------------------------
        | 12. ERROR
        |--------------------------------------------------------------------------
        */

            report($e);

            return response()->json([
                'success' => false,
                'message' =>
                $e->getMessage(),
            ], 422);
        }
    }
}
