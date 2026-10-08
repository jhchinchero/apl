<?php

namespace App\Domains\Inventario\Controllers;




use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use App\Domains\Inventario\Models\Product;
use App\Domains\Inventario\Models\StockProduct;
use App\Domains\Inventario\Models\StockMovement;
use App\Domains\Empresa\Models\Branch;
use Illuminate\Support\Facades\Auth;
use App\Domains\Seguridad\Models\Access;

use Illuminate\Support\Facades\Log;
use Throwable;
use Illuminate\Support\Facades\Validator;



class StockProductController extends Controller
{
    /**
     * Actualizar precio, costo y/o stock de un producto.
     *
     * Tipos:
     *
     * PRECIO_COSTO
     * - Actualiza solamente precio y costo.
     *
     * AJUSTE
     * - quantity representa el STOCK FINAL.
     *
     * AMBOS
     * - Actualiza precio y costo.
     * - quantity representa una CANTIDAD A INGRESAR.
     * - Se suma al stock actual.
     */
    public function updateStock(Request $request)
    {
        try {

            /*
            |--------------------------------------------------------------------------
            | USUARIO
            |--------------------------------------------------------------------------
            */

            $user = Auth::user();

            if (!$user) {

                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado.'
                ], 401);
            }


            /*
            |--------------------------------------------------------------------------
            | EMPRESA
            |--------------------------------------------------------------------------
            */

            $companyId =
                $user->id_company;


            /*
            |--------------------------------------------------------------------------
            | VALIDACIÓN
            |--------------------------------------------------------------------------
            */

            $validator = Validator::make(
                $request->all(),
                [

                    'id_product' => [
                        'required',
                        'integer'
                    ],

                    'type' => [
                        'required',
                        'in:AJUSTE,PRECIO_COSTO,AMBOS'
                    ],

                    'price' => [
                        'nullable',
                        'numeric',
                        'min:0'
                    ],

                    'cost' => [
                        'nullable',
                        'numeric',
                        'min:0'
                    ],

                    'items' => [
                        'nullable',
                        'array'
                    ],

                    'items.*.id_branch' => [
                        'required',
                        'integer'
                    ],

                    'items.*.quantity' => [
                        'required',
                        'numeric',
                        'min:0'
                    ],
                    'items.*.comment' => [
    'nullable',
    'string',
    'max:255'
],

                ]
            );

            $access = Access::where('user_id', $user->id)
                ->where('module_id', 4) //modulo 4 inevtario catalogo
                ->first();

            if (!$access->can_edit) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para modificar el stock.',
                    'data' => []
                ], 422);
            }






            /*
            |--------------------------------------------------------------------------
            | VALIDACIÓN FALLÓ
            |--------------------------------------------------------------------------
            */

            if ($validator->fails()) {

                return response()->json([
                    'success' => false,
                    'message' => 'Los datos enviados no son válidos.',
                    'errors' => $validator->errors()
                ], 422);
            }


            $validated =
                $validator->validated();


            /*
            |--------------------------------------------------------------------------
            | DATOS
            |--------------------------------------------------------------------------
            */

            $productId =
                (int) $validated['id_product'];

            $type =
                $validated['type'];


            /*
            |--------------------------------------------------------------------------
            | PRECIO Y COSTO
            |--------------------------------------------------------------------------
            */

            if (
                in_array(
                    $type,
                    [
                        'PRECIO_COSTO',
                        'AMBOS'
                    ],
                    true
                )
            ) {

                if (
                    !array_key_exists(
                        'price',
                        $validated
                    )
                    ||
                    !array_key_exists(
                        'cost',
                        $validated
                    )
                ) {

                    return response()->json([
                        'success' => false,
                        'message' =>
                        'El precio y costo son obligatorios.'
                    ], 422);
                }
            }


            /*
            |--------------------------------------------------------------------------
            | ITEMS
            |--------------------------------------------------------------------------
            */

            if (
                in_array(
                    $type,
                    [
                        'AJUSTE',
                        'AMBOS'
                    ],
                    true
                )
            ) {

                if (
                    empty($validated['items'])
                ) {

                    return response()->json([
                        'success' => false,
                        'message' =>
                        'Debe seleccionar al menos una sucursal.'
                    ], 422);
                }
            }


            /*
            |--------------------------------------------------------------------------
            | PRODUCTO
            |--------------------------------------------------------------------------
            */

            $product =
                Product::query()
                ->where(
                    'id_company',
                    $companyId
                )
                ->where(
                    'id',
                    $productId
                )
                ->first();


            if (!$product) {

                return response()->json([
                    'success' => false,
                    'message' =>
                    'El producto no existe en esta empresa.'
                ], 404);
            }


            /*
            |--------------------------------------------------------------------------
            | TRANSACCIÓN
            |--------------------------------------------------------------------------
            */

            return DB::transaction(
                function () use (
                    $validated,
                    $type,
                    $product,
                    $companyId,
                    $user
                ) {

                    /*
                    |--------------------------------------------------------------------------
                    | RESULTADOS
                    |--------------------------------------------------------------------------
                    */

                    $movements = [];


                    /*
                    |--------------------------------------------------------------------------
                    | PRECIO Y COSTO
                    |--------------------------------------------------------------------------
                    */

                    if (
                        in_array(
                            $type,
                            [
                                'PRECIO_COSTO',
                                'AMBOS'
                            ],
                            true
                        )
                    ) {

                        $product->update([

                            'sale_price' =>
                            (float)
                            $validated['price'],

                            'purchase_price' =>
                            (float)
                            $validated['cost']

                        ]);
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | SOLO PRECIO Y COSTO
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $type ===
                        'PRECIO_COSTO'
                    ) {

                        return response()->json([
                            'success' => true,
                            'message' =>
                            'Precio y costo actualizados correctamente.',
                            'data' => [
                                'product_id' =>
                                $product->id,

                                'price' =>
                                (float)
                                $product->sale_price,

                                'cost' =>
                                (float)
                                $product->purchase_price,

                                'movements' =>
                                []
                            ]
                        ]);
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | RECORRER SUCURSALES
                    |--------------------------------------------------------------------------
                    */

                    foreach (
                        $validated['items']
                        as $item
                    ) {

                        /*
                        |--------------------------------------------------------------------------
                        | SUCURSAL
                        |--------------------------------------------------------------------------
                        */

                        $branchId =
                            (int)
                            $item['id_branch'];


                        /*
                        |--------------------------------------------------------------------------
                        | CANTIDAD RECIBIDA
                        |--------------------------------------------------------------------------
                        */

                        $quantity =
                            (float)
                            $item['quantity'];

                            $comment = isset($item['comment'])
                            ? trim($item['comment'])
                            : null;


                        /*
                        |--------------------------------------------------------------------------
                        | VERIFICAR SUCURSAL
                        |--------------------------------------------------------------------------
                        */

                        $branch =
                            Branch::query()
                            ->where(
                                'id_company',
                                $companyId
                            )
                            ->where(
                                'id',
                                $branchId
                            )
                            ->first();


                        if (!$branch) {

                            throw new \Exception(
                                "La sucursal {$branchId} no pertenece a la empresa."
                            );
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | STOCK
                        |--------------------------------------------------------------------------
                        |
                        | lockForUpdate evita que dos procesos modifiquen
                        | simultáneamente el mismo stock.
                        |
                        */

                        $stock =
                            StockProduct::query()
                            ->where(
                                'id_company',
                                $companyId
                            )
                            ->where(
                                'branch_id',
                                $branchId
                            )
                            ->where(
                                'product_id',
                                $product->id
                            )
                            ->lockForUpdate()
                            ->first();


                        /*
                        |--------------------------------------------------------------------------
                        | CREAR STOCK SI NO EXISTE
                        |--------------------------------------------------------------------------
                        */

                        if (!$stock) {

                            $stock =
                                StockProduct::create([

                                    'id_company' =>
                                    $companyId,

                                    'branch_id' =>
                                    $branchId,

                                    'product_id' =>
                                    $product->id,

                                    'current_stock' =>
                                    0,

                                    'status' =>
                                    1,

                                    'price' =>
                                    (float)
                                    ($product->sale_price ?? 0),

                                    'location' =>
                                    null

                                ]);
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | STOCK ANTERIOR
                        |--------------------------------------------------------------------------
                        */

                        $stockAnterior =
                            (float)
                            $stock->current_stock;


                        /*
                        |--------------------------------------------------------------------------
                        | ======================================================
                        | AJUSTE
                        | ======================================================
                        |--------------------------------------------------------------------------
                        |
                        | quantity = STOCK FINAL
                        |
                        | NO se suma.
                        |
                        | Ejemplo:
                        |
                        | anterior = 10
                        | usuario = 5
                        |
                        | nuevo = 5
                        | diferencia = -5
                        |
                        */

                        if (
                            $type ===
                            'AJUSTE'
                        ) {

                            /*
                            |--------------------------------------------------------------------------
                            | STOCK FINAL
                            |--------------------------------------------------------------------------
                            */


                            

                            $stockNuevo =
                                $quantity;


                            /*
                            |--------------------------------------------------------------------------
                            | DIFERENCIA
                            |--------------------------------------------------------------------------
                            */

                            $diferencia =
                                $stockNuevo -
                                $stockAnterior;


                            /*
                            |--------------------------------------------------------------------------
                            | SIN CAMBIO
                            |--------------------------------------------------------------------------
                            */

                            if (
                                abs($diferencia)
                                < 0.000001
                            ) {

                                $movements[] = [

                                    'branch_id' =>
                                    $branchId,

                                    'stock_anterior' =>
                                    $stockAnterior,

                                    'quantity' =>
                                    0,

                                    'difference' =>
                                    0,

                                    'stock_actual' =>
                                    $stockAnterior,

                                    'movement_type' =>
                                    'ADJUSTMENT',

                                    'movement_id' =>
                                    null,

                                    'message' =>
                                    'El stock ya tiene la cantidad indicada.'

                                ];

                                continue;
                            }


                            /*
                            |--------------------------------------------------------------------------
                            | ACTUALIZAR STOCK
                            |--------------------------------------------------------------------------
                            */

                            $stock->update([

                                'current_stock' =>
                                $stockNuevo,

                                'price' =>
                                (float)
                                ($product->sale_price ?? 0)

                            ]);


                            /*
                            |--------------------------------------------------------------------------
                            | TEXTO
                            |--------------------------------------------------------------------------
                            */

                            if (
                                $diferencia > 0
                            ) {

                                $note =
                                    'Ajuste de stock por aumento.'
                                    . ' Stock anterior: '
                                    . $stockAnterior
                                    . '. Stock nuevo: '
                                    . $stockNuevo
                                    . '. Diferencia: +'
                                    . $diferencia;
                            } else {

                                $note =
                                    'Ajuste de stock por disminución.'
                                    . ' Stock anterior: '
                                    . $stockAnterior
                                    . '. Stock nuevo: '
                                    . $stockNuevo
                                    . '. Diferencia: '
                                    . $diferencia;
                            }

                             // Comentario del usuario
                        if ($comment) {
                            $note .= ' Comentario: ' . $comment;
                        }


                            /*
                            |--------------------------------------------------------------------------
                            | REGISTRAR KARDEX
                            |--------------------------------------------------------------------------
                            |
                            | quantity es SIGNED:
                            |
                            | +5 = aumento
                            | -5 = disminución
                            |
                            */

                            $movement =
                                StockMovement::create([

                                    'id_company' =>
                                    $companyId,

                                    'branch_id' =>
                                    $branchId,

                                    'product_id' =>
                                    $product->id,

                                    'user_id' =>
                                    $user->id,

                                    'movement_type' =>
                                    'ADJUSTMENT',

                                    'quantity' =>
                                    $diferencia,

                                    'reference_type' =>
                                    'product',

                                    'reference_id' =>
                                    $product->id,

                                    'note' =>
                                    $note

                                ]);


                            /*
                            |--------------------------------------------------------------------------
                            | RESULTADO
                            |--------------------------------------------------------------------------
                            */

                            $movements[] = [

                                'branch_id' =>
                                $branchId,

                                'stock_anterior' =>
                                $stockAnterior,

                                'quantity' =>
                                $diferencia,

                                'difference' =>
                                $diferencia,

                                'stock_actual' =>
                                $stockNuevo,

                                'movement_type' =>
                                'ADJUSTMENT',

                                'movement_id' =>
                                $movement->id,

                                'message' =>
                                $diferencia > 0
                                    ? 'Stock aumentado mediante ajuste.'
                                    : 'Stock disminuido mediante ajuste.'

                            ];

                            continue;
                        }

                        


                        /*
                        |--------------------------------------------------------------------------
                        | ======================================================
                        | AMBOS
                        | ======================================================
                        |--------------------------------------------------------------------------
                        |
                        | quantity = CANTIDAD A AGREGAR
                        |
                        | Ejemplo:
                        |
                        | anterior = 10
                        | quantity = 5
                        |
                        | nuevo = 15
                        |
                        */

                        if (
                            $type ===
                            'AMBOS'
                        ) {

                            /*
                            |--------------------------------------------------------------------------
                            | STOCK NUEVO
                            |--------------------------------------------------------------------------
                            */

                            $stockNuevo =
                                $stockAnterior +
                                $quantity;


                            /*
                            |--------------------------------------------------------------------------
                            | ACTUALIZAR STOCK
                            |--------------------------------------------------------------------------
                            */

                            $stock->update([

                                'current_stock' =>
                                $stockNuevo,

                                'price' =>
                                (float)
                                ($product->sale_price ?? 0)

                            ]);


                            /*
                            |--------------------------------------------------------------------------
                            | SI HAY CANTIDAD
                            |--------------------------------------------------------------------------
                            */

                            if (
                                $quantity > 0
                            ) {

                                $note =
                                    'Ingreso de stock junto con actualización de precio y costo.'
                                    . ' Stock anterior: '
                                    . $stockAnterior
                                    . '. Cantidad ingresada: +'
                                    . $quantity
                                    . '. Stock nuevo: '
                                    . $stockNuevo;

                                    // Comentario del usuario
                            if ($comment) {
                                $note .= ' Comentario: ' . $comment;
                            }


                                /*
                                |--------------------------------------------------------------------------
                                | REGISTRAR MOVIMIENTO
                                |--------------------------------------------------------------------------
                                */

                                $movement =
                                    StockMovement::create([

                                        'id_company' =>
                                        $companyId,

                                        'branch_id' =>
                                        $branchId,

                                        'product_id' =>
                                        $product->id,

                                        'user_id' =>
                                        $user->id,

                                        'movement_type' =>
                                        'ENTRY',

                                        'quantity' =>
                                        $quantity,

                                        'reference_type' =>
                                        'product',

                                        'reference_id' =>
                                        $product->id,

                                        'note' =>
                                        $note

                                    ]);


                                /*
                                |--------------------------------------------------------------------------
                                | RESULTADO
                                |--------------------------------------------------------------------------
                                */

                                $movements[] = [

                                    'branch_id' =>
                                    $branchId,

                                    'stock_anterior' =>
                                    $stockAnterior,

                                    'quantity' =>
                                    $quantity,

                                    'difference' =>
                                    $quantity,

                                    'stock_actual' =>
                                    $stockNuevo,

                                    'movement_type' =>
                                    'ENTRY',

                                    'movement_id' =>
                                    $movement->id,

                                    'message' =>
                                    'Stock incrementado correctamente.'

                                ];
                            } else {

                                /*
                                |--------------------------------------------------------------------------
                                | CANTIDAD 0
                                |--------------------------------------------------------------------------
                                */

                                $movements[] = [

                                    'branch_id' =>
                                    $branchId,

                                    'stock_anterior' =>
                                    $stockAnterior,

                                    'quantity' =>
                                    0,

                                    'difference' =>
                                    0,

                                    'stock_actual' =>
                                    $stockNuevo,

                                    'movement_type' =>
                                    'ENTRY',

                                    'movement_id' =>
                                    null,

                                    'message' =>
                                    'No se agregó stock porque la cantidad es 0.'

                                ];
                            }
                        }
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | RESPUESTA
                    |--------------------------------------------------------------------------
                    */

                    return response()->json([

                        'success' =>
                        true,

                        'message' =>
                        'Inventario actualizado correctamente.',

                        'data' => [

                            'product_id' =>
                            $product->id,

                            'product' =>
                            $product->fresh(),

                            'type' =>
                            $type,

                            'movements' =>
                            $movements

                        ]

                    ]);
                }
            );
        }

        /*
        |--------------------------------------------------------------------------
        | VALIDACIÓN
        |--------------------------------------------------------------------------
        */ catch (
            \Illuminate\Validation\ValidationException $e
        ) {

            return response()->json([

                'success' =>
                false,

                'message' =>
                'Los datos enviados no son válidos.',

                'errors' =>
                $e->errors()

            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | ERROR GENERAL
        |--------------------------------------------------------------------------
        */ catch (
            \Throwable $e
        ) {

            Log::error(
                'Error al actualizar inventario',
                [

                    'user_id' =>
                    Auth::id(),

                    'error' =>
                    $e->getMessage(),

                    'file' =>
                    $e->getFile(),

                    'line' =>
                    $e->getLine(),

                    'request' =>
                    $request->all()

                ]
            );


            return response()->json([

                'success' =>
                false,

                'message' =>
                'Error al actualizar el inventario.',

                'error' =>
                $e->getMessage()

            ], 500);
        }
    }
}
