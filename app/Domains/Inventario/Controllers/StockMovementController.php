<?php


namespace App\Domains\Inventario\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Domains\Inventario\Models\StockMovement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class StockMovementController extends Controller
{
   

 public function index(Request $request)
    {
        try {

            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado.'
                ], 401);
            }

            /*
            |--------------------------------------------------------------------------
            | VALIDACIÓN
            |--------------------------------------------------------------------------
            */

            $validated = $request->validate([
                'product_id' => [
                    'required',
                    'integer'
                ],

                'branch_id' => [
                    'required',
                    'integer'
                ],

                'user_id' => [
                    'nullable',
                    'integer'
                ],

                'movement_type' => [
                    'nullable',
                    'string'
                ],
            ]);

            /*
            |--------------------------------------------------------------------------
            | CONSULTA
            |--------------------------------------------------------------------------
            */

            $query = StockMovement::query()
                ->where('id_company', $user->id_company)
                ->where('product_id', $validated['product_id'])
                ->where('branch_id', $validated['branch_id'])
                ->with([
                    'user',
                    'branch',
                    'product'
                ]);

            /*
            |--------------------------------------------------------------------------
            | FILTRO POR USUARIO
            |--------------------------------------------------------------------------
            */

            if (!empty($validated['user_id'])) {
                $query->where(
                    'user_id',
                    $validated['user_id']
                );
            }

            /*
            |--------------------------------------------------------------------------
            | FILTRO POR TIPO DE MOVIMIENTO
            |--------------------------------------------------------------------------
            */

            if (!empty($validated['movement_type'])) {
                $query->where(
                    'movement_type',
                    $validated['movement_type']
                );
            }

            /*
            |--------------------------------------------------------------------------
            | ORDEN CRONOLÓGICO
            |--------------------------------------------------------------------------
            |
            | Es importante ordenar ASC porque el stock actual del Kardex
            | se calcula movimiento por movimiento.
            |
            */

            $movements = $query
                ->orderBy('created_at', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            /*
            |--------------------------------------------------------------------------
            | CÁLCULO DEL KARDEX
            |--------------------------------------------------------------------------
            */

            $stockActual = 0;

            /*
            |--------------------------------------------------------------------------
            | MOVIMIENTOS QUE REPRESENTAN ENTRADAS
            |--------------------------------------------------------------------------
            */

            $entradas = [
                'INITIAL_STOCK',
                'PURCHASE',
                'ENTRY',
                'IN',
                'TRANSFER_IN',
                'RETURN',
                'INGRESO',
            ];

            /*
            |--------------------------------------------------------------------------
            | MOVIMIENTOS QUE REPRESENTAN SALIDAS
            |--------------------------------------------------------------------------
            */

            $salidas = [
                'SALE',
                'EXIT',
                'OUT',
                'TRANSFER_OUT',
                'SALIDA',
            ];

            /*
            |--------------------------------------------------------------------------
            | CONSTRUIR KARDEX
            |--------------------------------------------------------------------------
            */

            $kardex = $movements->map(function ($movement) use (
                &$stockActual,
                $entradas,
                $salidas
            ) {

                $movementType = strtoupper(
                    trim($movement->movement_type ?? '')
                );

                $quantity = (float) $movement->quantity;

                $entrada = 0;
                $salida = 0;
                $tipo = 'OTRO';

                /*
                |--------------------------------------------------------------------------
                | ENTRADAS NORMALES
                |--------------------------------------------------------------------------
                */

                if (in_array($movementType, $entradas, true)) {

                    $entrada = abs($quantity);

                    $salida = 0;

                    $stockActual += $entrada;

                    $tipo = 'INGRESO';
                }

                /*
                |--------------------------------------------------------------------------
                | SALIDAS NORMALES
                |--------------------------------------------------------------------------
                */

                elseif (in_array($movementType, $salidas, true)) {

                    $entrada = 0;

                    $salida = abs($quantity);

                    $stockActual -= $salida;

                    $tipo = 'SALIDA';
                }

                /*
                |--------------------------------------------------------------------------
                | AJUSTE DE STOCK
                |--------------------------------------------------------------------------
                |
                | IMPORTANTE:
                |
                | ADJUSTMENT quantity > 0
                | = aumento de stock
                |
                | ADJUSTMENT quantity < 0
                | = disminución de stock
                |
                | Ejemplo:
                |
                | Stock 10
                | Ajuste a 5
                | quantity = -5
                |
                | 10 - 5 = 5
                |
                |--------------------------------------------------------------------------
                */

                elseif ($movementType === 'ADJUSTMENT') {

                    /*
                    |--------------------------------------------------------------------------
                    | AJUSTE POSITIVO
                    |--------------------------------------------------------------------------
                    */

                    if ($quantity > 0) {

                        $entrada = $quantity;

                        $salida = 0;

                        $stockActual += $quantity;

                        $tipo = 'INGRESO';
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | AJUSTE NEGATIVO
                    |--------------------------------------------------------------------------
                    */

                    elseif ($quantity < 0) {

                        $entrada = 0;

                        $salida = abs($quantity);

                        $stockActual -= abs($quantity);

                        $tipo = 'SALIDA';
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | AJUSTE CERO
                    |--------------------------------------------------------------------------
                    */

                    else {

                        $entrada = 0;

                        $salida = 0;

                        $tipo = 'OTRO';
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | OTROS MOVIMIENTOS
                |--------------------------------------------------------------------------
                */

                else {

                    $entrada = 0;

                    $salida = 0;

                    $tipo = 'OTRO';
                }

                /*
                |--------------------------------------------------------------------------
                | EVITAR -0
                |--------------------------------------------------------------------------
                */

                if (abs($stockActual) < 0.000001) {
                    $stockActual = 0;
                }

                /*
                |--------------------------------------------------------------------------
                | RESULTADO DEL MOVIMIENTO
                |--------------------------------------------------------------------------
                */

                return [

                    'id' => $movement->id,

                    'fecha' => $movement->created_at
                        ? $movement->created_at->format('d/m/Y H:i')
                        : null,

                    'movement_type' => $movement->movement_type,

                    'tipo' => $tipo,

                    /*
                    |--------------------------------------------------------------------------
                    | CANTIDAD ORIGINAL
                    |--------------------------------------------------------------------------
                    */

                    'cantidad' => $quantity,

                    /*
                    |--------------------------------------------------------------------------
                    | ENTRADA
                    |--------------------------------------------------------------------------
                    */

                    'entrada' => $entrada,

                    /*
                    |--------------------------------------------------------------------------
                    | SALIDA
                    |--------------------------------------------------------------------------
                    */

                    'salida' => $salida,

                    /*
                    |--------------------------------------------------------------------------
                    | STOCK ACUMULADO
                    |--------------------------------------------------------------------------
                    */

                    'stock_actual' => $stockActual,

                    /*
                    |--------------------------------------------------------------------------
                    | PRODUCTO
                    |--------------------------------------------------------------------------
                    */

                    'product_id' => $movement->product_id,

                    'producto' => $movement->product,

                    /*
                    |--------------------------------------------------------------------------
                    | SUCURSAL
                    |--------------------------------------------------------------------------
                    */

                    'branch_id' => $movement->branch_id,

                    'sucursal' => $movement->branch,

                    /*
                    |--------------------------------------------------------------------------
                    | USUARIO
                    |--------------------------------------------------------------------------
                    */

                    'user_id' => $movement->user_id,

                    'usuario' => $movement->user,

                    'usuario_nombre' => $movement->user?->name
                        ?? 'Sin usuario',

                    /*
                    |--------------------------------------------------------------------------
                    | REFERENCIA
                    |--------------------------------------------------------------------------
                    */

                    'reference_type' => $movement->reference_type,

                    'reference_id' => $movement->reference_id,

                    /*
                    |--------------------------------------------------------------------------
                    | OBSERVACIÓN
                    |--------------------------------------------------------------------------
                    */

                    'note' => $movement->note,

                    /*
                    |--------------------------------------------------------------------------
                    | FECHAS
                    |--------------------------------------------------------------------------
                    */

                    'created_at' => $movement->created_at,

                    'updated_at' => $movement->updated_at,
                ];
            });

            /*
            |--------------------------------------------------------------------------
            | RESPUESTA
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'success' => true,

                'message' => 'Movimientos obtenidos correctamente.',

                'data' => $kardex->values(),

                /*
                |--------------------------------------------------------------------------
                | RESUMEN
                |--------------------------------------------------------------------------
                */

                'summary' => [
                    'stock_actual' => $stockActual,

                    'total_movimientos' => $kardex->count(),

                    'total_entradas' => $kardex->sum(
                        fn ($item) => (float) $item['entrada']
                    ),

                    'total_salidas' => $kardex->sum(
                        fn ($item) => (float) $item['salida']
                    ),
                ],
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Los datos enviados no son válidos.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Throwable $e) {

            Log::error(
                'Error al obtener Kardex de producto',
                [
                    'user_id' => Auth::id(),
                    'product_id' => $request->product_id,
                    'branch_id' => $request->branch_id,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'No se pudo obtener el historial de movimientos.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Mostrar un movimiento específico
     */
    public function show(
        Request $request,
        $id
    ) {

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.'
            ], 401);
        }

        $companyId = $user->id_company;

        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' =>
                'El usuario no tiene una empresa asociada.'
            ], 422);
        }

        $movement = StockMovement::with([
            'company',
            'branch',
            'product',
            'user'
        ])
            ->where('id_company', $companyId)
            ->where('id', $id)
            ->first();

        if (!$movement) {
            return response()->json([
                'success' => false,
                'message' => 'Movimiento no encontrado.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $movement
        ]);
    }
}
