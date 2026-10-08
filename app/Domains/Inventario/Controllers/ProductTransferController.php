<?php

namespace App\Domains\Inventario\Controllers;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

use App\Domains\Inventario\Models\ProductTransfer;
use App\Domains\Inventario\Models\ProductTransferItem;
use App\Domains\Inventario\Models\Product;
use App\Domains\Inventario\Models\StockProduct;
use App\Domains\Inventario\Models\StockMovement;
use App\Domains\Empresa\Models\Branch;

class ProductTransferController extends Controller
{
    /**
     * ============================================================
     * LISTAR TRANSFERENCIAS
     * ============================================================
     */
    public function index(Request $request)
    {
        try {

            /*
        |--------------------------------------------------------------------------
        | USUARIO AUTENTICADO
        |--------------------------------------------------------------------------
        */

            $user = Auth::user();

            if (!$user) {

                return response()->json([
                    'message' => 'Usuario no autenticado.'
                ], 401);
            }


            /*
        |--------------------------------------------------------------------------
        | EMPRESA DEL USUARIO
        |--------------------------------------------------------------------------
        */

            $idCompany = $user->id_company;


            /*
        |--------------------------------------------------------------------------
        | SUCURSALES ASIGNADAS AL USUARIO
        |--------------------------------------------------------------------------
        |
        | user_branches:
        |
        | user_id
        | branch_id
        |
        */

            $userBranchIds = DB::table('user_branches')
                ->where('user_id', $user->id)
                ->pluck('branch_id')
                ->map(function ($id) {
                    return (int) $id;
                })
                ->toArray();


            /*
        |--------------------------------------------------------------------------
        | CONSULTA BASE
        |--------------------------------------------------------------------------
        */

            $query = ProductTransfer::query()

                /*
            |--------------------------------------------------------------
            | EMPRESA
            |--------------------------------------------------------------
            */

                ->where(
                    'id_company',
                    $idCompany
                )

                /*
            |--------------------------------------------------------------
            | SOLO TRANSFERENCIAS DEL USUARIO
            |--------------------------------------------------------------
            |
            | ENVIADAS:
            | user_id = usuario autenticado
            |
            | RECIBIDAS:
            | destination_branch_id pertenece a una sucursal
            | asignada al usuario.
            |
            */

                ->where(function ($q) use (
                    $user,
                    $userBranchIds
                ) {

                    /*
                | ENVIADAS
                */

                    $q->where(
                        'user_id',
                        $user->id
                    );


                    /*
                | RECIBIDAS
                */

                    if (!empty($userBranchIds)) {

                        $q->orWhereIn(
                            'destination_branch_id',
                            $userBranchIds
                        );
                    }
                })


                /*
            |--------------------------------------------------------------------------
            | RELACIONES
            |--------------------------------------------------------------------------
            */

                ->with([

                    /*
                | Sucursal origen
                */

                    'originBranch:id,code_branch,branch_name',

                    /*
                | Sucursal destino
                */

                    'destinationBranch:id,code_branch,branch_name',

                    /*
                | Usuario que creó la transferencia
                */

                    'user:id,name',

                    /*
                | Usuario que recibió
                */

                    'receivedByUser:id,name',

                    /*
                | Productos
                */

                    'items' => function ($query) {

                        $query->with([
                            'product:id,code_product,name'
                        ]);
                    }

                ])

                /*
            |--------------------------------------------------------------------------
            | MÁS RECIENTES PRIMERO
            |--------------------------------------------------------------------------
            */

                ->orderByDesc('id');


            /*
        |--------------------------------------------------------------------------
        | FILTRO TIPO
        |--------------------------------------------------------------------------
        */

            if ($request->filled('type')) {

                /*
            |--------------------------------------------------------------------------
            | ENVIADAS
            |--------------------------------------------------------------------------
            */

                if ($request->type === 'sent') {

                    $query->where(
                        'user_id',
                        $user->id
                    );
                }


                /*
            |--------------------------------------------------------------------------
            | RECIBIDAS
            |--------------------------------------------------------------------------
            */ elseif ($request->type === 'received') {

                    if (!empty($userBranchIds)) {

                        $query->whereIn(
                            'destination_branch_id',
                            $userBranchIds
                        );
                    } else {

                        /*
                    | El usuario no tiene sucursales asignadas.
                    */

                        $query->whereRaw(
                            '1 = 0'
                        );
                    }
                }
            }


            /*
        |--------------------------------------------------------------------------
        | FILTRO ESTADO
        |--------------------------------------------------------------------------
        */

            if ($request->filled('status')) {

                $query->where(
                    'status',
                    $request->status
                );
            }


            /*
        |--------------------------------------------------------------------------
        | BÚSQUEDA
        |--------------------------------------------------------------------------
        */

            if ($request->filled('search')) {

                $search = trim(
                    $request->search
                );


                $query->where(function ($q) use ($search) {

                    /*
                | Código transferencia
                */

                    $q->where(
                        'code_transfer',
                        'LIKE',
                        "%{$search}%"
                    );


                    /*
                | Sucursal origen
                */

                    $q->orWhereHas(
                        'originBranch',
                        function ($branchQuery) use ($search) {

                            $branchQuery
                                ->where(
                                    'branch_name',
                                    'LIKE',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'code_branch',
                                    'LIKE',
                                    "%{$search}%"
                                );
                        }
                    );


                    /*
                | Sucursal destino
                */

                    $q->orWhereHas(
                        'destinationBranch',
                        function ($branchQuery) use ($search) {

                            $branchQuery
                                ->where(
                                    'branch_name',
                                    'LIKE',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'code_branch',
                                    'LIKE',
                                    "%{$search}%"
                                );
                        }
                    );
                });
            }


            /*
        |--------------------------------------------------------------------------
        | PAGINACIÓN
        |--------------------------------------------------------------------------
        */

            $perPage = (int) $request->get(
                'per_page',
                10
            );


            /*
        | Mínimo 1
        | Máximo 100
        */

            $perPage = min(
                max($perPage, 1),
                100
            );


            $transfers = $query->paginate(
                $perPage
            );


            /*
        |--------------------------------------------------------------------------
        | DETERMINAR TIPO
        |--------------------------------------------------------------------------
        */

            $transfers->getCollection()->transform(
                function ($transfer) use (
                    $user,
                    $userBranchIds
                ) {

                    /*
                |--------------------------------------------------------------------------
                | SI EL USUARIO CREÓ LA TRANSFERENCIA
                |--------------------------------------------------------------------------
                */

                    if (
                        (int) $transfer->user_id ===
                        (int) $user->id
                    ) {

                        $transfer->type = 'sent';
                    }


                    /*
                |--------------------------------------------------------------------------
                | SI LA TRANSFERENCIA LLEGA A UNA SUCURSAL DEL USUARIO
                |--------------------------------------------------------------------------
                */ elseif (
                        in_array(
                            (int) $transfer->destination_branch_id,
                            $userBranchIds,
                            true
                        )
                    ) {

                        $transfer->type = 'received';
                    }


                    /*
                |--------------------------------------------------------------------------
                | SEGURIDAD
                |--------------------------------------------------------------------------
                */ else {

                        $transfer->type = null;
                    }


                    return $transfer;
                }
            );


            /*
        |--------------------------------------------------------------------------
        | RESPUESTA
        |--------------------------------------------------------------------------
        */

            return response()->json([

                'message' =>
                'Transferencias obtenidas correctamente.',

                'data' => $transfers

            ], 200);
        } catch (\Throwable $e) {

            /*
        |--------------------------------------------------------------------------
        | ERROR
        |--------------------------------------------------------------------------
        */

            return response()->json([

                'message' =>
                'Error al obtener las transferencias.',

                'error' =>
                $e->getMessage()

            ], 500);
        }
    }

    /**
     * ============================================================
     * MOSTRAR UNA TRANSFERENCIA
     * ============================================================
     */
    public function show($id)
    {
        try {

            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'message' => 'Usuario no autenticado.'
                ], 401);
            }

            $idCompany = $user->id_company;

            /*
            |--------------------------------------------------------------------------
            | SUCURSALES DEL USUARIO
            |--------------------------------------------------------------------------
            */
            $branchIds = DB::table('user_branches')
                ->join(
                    'branches',
                    'branches.id',
                    '=',
                    'user_branches.branch_id'
                )
                ->where('user_branches.user_id', $user->id)
                ->where('branches.id_company', $idCompany)
                ->where('branches.status', true)
                ->pluck('branches.id')
                ->toArray();

            /*
            |--------------------------------------------------------------------------
            | BUSCAR TRANSFERENCIA
            |--------------------------------------------------------------------------
            */
            $transfer = ProductTransfer::query()
                ->where('product_transfers.id', $id)
                ->where('product_transfers.id_company', $idCompany)
                ->where(function ($q) use ($branchIds, $user) {

                    $q->where(
                        'product_transfers.user_id',
                        $user->id
                    );

                    if (!empty($branchIds)) {
                        $q->orWhereIn(
                            'product_transfers.origin_branch_id',
                            $branchIds
                        );

                        $q->orWhereIn(
                            'product_transfers.destination_branch_id',
                            $branchIds
                        );
                    }
                })
                ->with([
                    'originBranch:id,code_branch,branch_name',
                    'destinationBranch:id,code_branch,branch_name',
                    'user:id,name',
                    'receivedByUser:id,name',
                    'items.product:id,code_product,name'
                ])
                ->first();

            if (!$transfer) {

                return response()->json([
                    'message' => 'Transferencia no encontrada.'
                ], 404);
            }

            return response()->json([
                'message' => 'Transferencia obtenida correctamente.',
                'data' => $transfer
            ], 200);
        } catch (\Throwable $e) {

            return response()->json([
                'message' => 'Error al obtener la transferencia.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * ============================================================
     * CREAR TRANSFERENCIA
     * ============================================================
     *
     * IMPORTANTE:
     *
     * Aquí NO se modifica el stock.
     *
     * El stock solamente se modifica cuando la transferencia
     * sea ACEPTADA.
     */
    public function store(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | VALIDACIÓN
        |--------------------------------------------------------------------------
        */
        $validator = Validator::make(
            $request->all(),
            [
                'origin_branch_id' => [
                    'required',
                    'integer',
                    'exists:branches,id'
                ],

                'destination_branch_id' => [
                    'required',
                    'integer',
                    'exists:branches,id'
                ],

                'items' => [
                    'required',
                    'array',
                    'min:1'
                ],

                'items.*.product_id' => [
                    'required',
                    'integer',
                    'exists:products,id'
                ],

                'items.*.quantity' => [
                    'required',
                    'numeric',
                    'gt:0'
                ],

                'items.*.price' => [
                    'nullable',
                    'numeric',
                    'min:0'
                ],
            ],
            [
                'origin_branch_id.required' =>
                'La sucursal de origen es obligatoria.',

                'destination_branch_id.required' =>
                'La sucursal de destino es obligatoria.',

                'destination_branch_id.different' =>
                'La sucursal de destino debe ser diferente a la sucursal de origen.',

                'items.required' =>
                'Debe agregar al menos un producto.',

                'items.min' =>
                'Debe agregar al menos un producto.',

                'items.*.product_id.required' =>
                'El producto es obligatorio.',

                'items.*.product_id.integer' =>
                'El ID del producto debe ser numérico.',

                'items.*.quantity.required' =>
                'La cantidad es obligatoria.',

                'items.*.quantity.numeric' =>
                'La cantidad debe ser numérica.',

                'items.*.quantity.gt' =>
                'La cantidad debe ser mayor a cero.',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | VALIDACIÓN EXTRA
        |--------------------------------------------------------------------------
        */
        $validator->after(function ($validator) use ($request) {

            if (
                $request->filled('origin_branch_id') &&
                $request->filled('destination_branch_id')
            ) {

                if (
                    (int) $request->origin_branch_id ===
                    (int) $request->destination_branch_id
                ) {

                    $validator->errors()->add(
                        'destination_branch_id',
                        'La sucursal de destino debe ser diferente a la sucursal de origen.'
                    );
                }
            }
        });

        if ($validator->fails()) {

            return response()->json([
                'message' => 'Los datos enviados no son válidos.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {

            $user = Auth::user();

            if (!$user) {

                return response()->json([
                    'message' => 'Usuario no autenticado.'
                ], 401);
            }

            $idCompany = $user->id_company;

            /*
            |--------------------------------------------------------------------------
            | TRANSACCIÓN
            |--------------------------------------------------------------------------
            */
            $transfer = DB::transaction(function () use (
                $request,
                $user,
                $idCompany
            ) {

                /*
                |--------------------------------------------------------------------------
                | VALIDAR SUCURSAL ORIGEN
                |--------------------------------------------------------------------------
                */
                $originBranch = Branch::query()
                    ->where('branches.id', $request->origin_branch_id)
                    ->where('branches.id_company', $idCompany)
                    ->where('branches.status', true)
                    ->first();

                if (!$originBranch) {

                    throw new \Exception(
                        'La sucursal de origen no existe, está inactiva o no pertenece a la empresa.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | VALIDAR QUE EL USUARIO PERTENEZCA A ORIGEN
                |--------------------------------------------------------------------------
                */
                $userBelongsToOrigin = DB::table('user_branches')
                    ->where('user_branches.user_id', $user->id)
                    ->where(
                        'user_branches.branch_id',
                        $request->origin_branch_id
                    )
                    ->exists();

                if (!$userBelongsToOrigin) {

                    throw new \Exception(
                        'El usuario no está autorizado para realizar transferencias desde la sucursal de origen.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | VALIDAR SUCURSAL DESTINO
                |--------------------------------------------------------------------------
                */
                $destinationBranch = Branch::query()
                    ->where(
                        'branches.id',
                        $request->destination_branch_id
                    )
                    ->where('branches.id_company', $idCompany)
                    ->where('branches.status', true)
                    ->first();

                if (!$destinationBranch) {

                    throw new \Exception(
                        'La sucursal de destino no existe, está inactiva o no pertenece a la empresa.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | GENERAR CÓDIGO DE TRANSFERENCIA
                |--------------------------------------------------------------------------
                |
                | Ejemplo:
                |
                | TRA-000001
                | TRA-000002
                |
                | El correlativo es independiente por empresa.
                |--------------------------------------------------------------------------
                */
                $lastTransfer = ProductTransfer::query()
                    ->where('id_company', $idCompany)
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();

                if ($lastTransfer) {

                    $lastNumber = (int) str_replace(
                        'TRA-',
                        '',
                        $lastTransfer->code_transfer
                    );

                    $nextNumber = $lastNumber + 1;
                } else {

                    $nextNumber = 1;
                }

                $codeTransfer =
                    'TRA-' .
                    str_pad(
                        $nextNumber,
                        6,
                        '0',
                        STR_PAD_LEFT
                    );

                /*
                |--------------------------------------------------------------------------
                | CREAR TRANSFERENCIA
                |--------------------------------------------------------------------------
                */
                $transfer = ProductTransfer::create([
                    'id_company' => $idCompany,
                    'user_id' => $user->id,

                    'origin_branch_id' =>
                    $originBranch->id,

                    'destination_branch_id' =>
                    $destinationBranch->id,

                    'received_by_user_id' => null,

                    'code_transfer' => $codeTransfer,

                    'status' => 'PENDIENTE',

                    'observation' =>
                    $request->observation ?? null,

                    'sent_at' => now(),

                    'received_at' => null,
                ]);

                /*
                |--------------------------------------------------------------------------
                | PROCESAR PRODUCTOS
                |--------------------------------------------------------------------------
                */
                foreach ($request->items as $item) {

                    /*
                    |--------------------------------------------------------------------------
                    | BUSCAR PRODUCTO
                    |--------------------------------------------------------------------------
                    |
                    | products.id
                    | NO products.id_product
                    |--------------------------------------------------------------------------
                    */
                    $product = Product::query()
                        ->where('products.id', $item['product_id'])
                        ->where(
                            'products.id_company',
                            $idCompany
                        )
                        ->where('products.status', true)
                        ->first();

                    if (!$product) {

                        throw new \Exception(
                            'El producto con ID ' .
                                $item['product_id'] .
                                ' no existe, está inactivo o no pertenece a la empresa.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | BUSCAR STOCK EN ORIGEN
                    |--------------------------------------------------------------------------
                    |
                    | product_stock.branch_id
                    | NO product_stock.id_branch
                    |--------------------------------------------------------------------------
                    */
                    $stock = StockProduct::query()
                        ->where(
                            'product_stock.id_company',
                            $idCompany
                        )
                        ->where(
                            'product_stock.branch_id',
                            $originBranch->id
                        )
                        ->where(
                            'product_stock.product_id',
                            $product->id
                        )
                        ->where(
                            'product_stock.status',
                            true
                        )
                        ->first();

                    /*
                    |--------------------------------------------------------------------------
                    | VALIDAR QUE EXISTA STOCK
                    |--------------------------------------------------------------------------
                    */
                    if (!$stock) {

                        throw new \Exception(
                            'El producto "' .
                                $product->name .
                                '" no tiene registro de stock en la sucursal de origen.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | VALIDAR CANTIDAD DISPONIBLE
                    |--------------------------------------------------------------------------
                    */
                    $quantity = (float) $item['quantity'];

                    if (
                        (float) $stock->current_stock <
                        $quantity
                    ) {

                        throw new \Exception(
                            'Stock insuficiente para el producto "' .
                                $product->name .
                                '". Disponible: ' .
                                $stock->current_stock .
                                ', solicitado: ' .
                                $quantity .
                                '.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | EVITAR PRODUCTOS DUPLICADOS
                    |--------------------------------------------------------------------------
                    */
                    $existingItem = $transfer->items()
                        ->where(
                            'product_id',
                            $product->id
                        )
                        ->first();

                    if ($existingItem) {

                        $existingItem->quantity =
                            (float) $existingItem->quantity +
                            $quantity;

                        $existingItem->save();
                    } else {

                        /*
                        |--------------------------------------------------------------------------
                        | CREAR ITEM
                        |--------------------------------------------------------------------------
                        */
                        ProductTransferItem::create([
                            'id_company' => $idCompany,

                            'product_transfer_id' =>
                            $transfer->id,

                            'product_id' =>
                            $product->id,

                            'quantity' =>
                            $quantity,

                            'price' =>
                            $item['price'] ?? $product->purchase_price,

                            'observation' =>
                            $item['observation'] ?? null,
                        ]);
                    }
                }

                return $transfer;
            });

            /*
            |--------------------------------------------------------------------------
            | RECARGAR TRANSFERENCIA
            |--------------------------------------------------------------------------
            */
            $transfer->load([
                'originBranch:id,code_branch,branch_name',
                'destinationBranch:id,code_branch,branch_name',
                'user:id,name',
                'receivedByUser:id,name',
                'items.product:id,code_product,name'
            ]);

            return response()->json([
                'message' =>
                'Transferencia creada correctamente.',

                'data' => $transfer
            ], 201);
        } catch (\Throwable $e) {

            return response()->json([
                'message' =>
                'Error al crear la transferencia.',

                'error' =>
                $e->getMessage()
            ], 500);
        }
    }


    /**
     * ============================================================
     * ACEPTAR TRANSFERENCIA
     * ============================================================
     */
   
/**
 * ============================================================
 * ACEPTAR TRANSFERENCIA
 * ============================================================
 */
public function accept($id)
{
    try {

        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no autenticado.'
            ], 401);
        }

        $idCompany = $user->id_company;

        $transfer = DB::transaction(function () use (
            $id,
            $user,
            $idCompany
        ) {

            /*
            |--------------------------------------------------------------------------
            | BUSCAR TRANSFERENCIA Y BLOQUEARLA
            |--------------------------------------------------------------------------
            */
            $transfer = ProductTransfer::query()
                ->where('product_transfers.id', $id)
                ->where(
                    'product_transfers.id_company',
                    $idCompany
                )
                ->lockForUpdate()
                ->first();

            if (!$transfer) {
                throw new \Exception(
                    'La transferencia no existe.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | SOLO PENDIENTE PUEDE ACEPTARSE
            |--------------------------------------------------------------------------
            */
            if ($transfer->status !== 'PENDIENTE') {
                throw new \Exception(
                    'La transferencia no puede aceptarse porque su estado actual es "' .
                    $transfer->status .
                    '".'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | VALIDAR QUE EL USUARIO PERTENEZCA A DESTINO
            |--------------------------------------------------------------------------
            */
            $userBelongsToDestination = DB::table('user_branches')
                ->join(
                    'branches',
                    'branches.id',
                    '=',
                    'user_branches.branch_id'
                )
                ->where(
                    'user_branches.user_id',
                    $user->id
                )
                ->where(
                    'user_branches.branch_id',
                    $transfer->destination_branch_id
                )
                ->where(
                    'branches.id_company',
                    $idCompany
                )
                ->where(
                    'branches.status',
                    true
                )
                ->exists();

            if (!$userBelongsToDestination) {
                throw new \Exception(
                    'No estás autorizado para recibir esta transferencia.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | OBTENER ITEMS
            |--------------------------------------------------------------------------
            */
            $items = ProductTransferItem::query()
                ->where(
                    'product_transfer_id',
                    $transfer->id
                )
                ->where(
                    'id_company',
                    $idCompany
                )
                ->get();

            if ($items->isEmpty()) {
                throw new \Exception(
                    'La transferencia no contiene productos.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | PROCESAR CADA PRODUCTO
            |--------------------------------------------------------------------------
            */
            foreach ($items as $item) {

                $quantity = (float) $item->quantity;

                if ($quantity <= 0) {
                    throw new \Exception(
                        'La cantidad del producto ID ' .
                        $item->product_id .
                        ' debe ser mayor que cero.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | STOCK ORIGEN
                |--------------------------------------------------------------------------
                */
                $originStock = StockProduct::query()
                    ->where(
                        'product_stock.id_company',
                        $idCompany
                    )
                    ->where(
                        'product_stock.branch_id',
                        $transfer->origin_branch_id
                    )
                    ->where(
                        'product_stock.product_id',
                        $item->product_id
                    )
                    ->where(
                        'product_stock.status',
                        true
                    )
                    ->lockForUpdate()
                    ->first();

                if (!$originStock) {
                    throw new \Exception(
                        'No existe stock del producto ID ' .
                        $item->product_id .
                        ' en la sucursal de origen.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | VALIDAR STOCK
                |--------------------------------------------------------------------------
                */
                if (
                    (float) $originStock->current_stock <
                    $quantity
                ) {
                    throw new \Exception(
                        'Stock insuficiente para completar la transferencia del producto ID ' .
                        $item->product_id .
                        '. Disponible: ' .
                        $originStock->current_stock .
                        ', solicitado: ' .
                        $quantity .
                        '.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | DESCONTAR STOCK ORIGEN
                |--------------------------------------------------------------------------
                */
                $originStock->current_stock =
                    (float) $originStock->current_stock -
                    $quantity;

                $originStock->save();

                /*
                |--------------------------------------------------------------------------
                | MOVIMIENTO DE SALIDA - ORIGEN
                |--------------------------------------------------------------------------
                |
                | IMPORTANTE:
                | Este registro permite que el Kardex de la sucursal
                | de origen muestre la salida por transferencia.
                |
                */
                StockMovement::create([
                    'id_company' => $idCompany,

                    'branch_id' =>
                        $transfer->origin_branch_id,

                    'product_id' =>
                        $item->product_id,

                    'user_id' =>
                        $user->id,

                    'movement_type' =>
                        'TRANSFER_OUT',

                    'quantity' =>
                        $quantity,

                    'reference_type' =>
                        'product_transfer',

                    'reference_id' =>
                        $transfer->id,

                    'note' =>
                        'Salida por transferencia aceptada hacia sucursal ' .
                        $transfer->destination_branch_id .
                        '.'
                ]);

                /*
                |--------------------------------------------------------------------------
                | BUSCAR STOCK DESTINO
                |--------------------------------------------------------------------------
                */
                $destinationStock = StockProduct::query()
                    ->where(
                        'product_stock.id_company',
                        $idCompany
                    )
                    ->where(
                        'product_stock.branch_id',
                        $transfer->destination_branch_id
                    )
                    ->where(
                        'product_stock.product_id',
                        $item->product_id
                    )
                    ->lockForUpdate()
                    ->first();

                /*
                |--------------------------------------------------------------------------
                | CREAR STOCK DESTINO SI NO EXISTE
                |--------------------------------------------------------------------------
                */
                if (!$destinationStock) {

                    $destinationStock = StockProduct::create([
                        'id_company' =>
                            $idCompany,

                        'branch_id' =>
                            $transfer->destination_branch_id,

                        'product_id' =>
                            $item->product_id,

                        'current_stock' =>
                            $quantity,

                        'status' =>
                            true,

                        'price' =>
                            $item->price ?? 0,

                        'location' =>
                            null,
                    ]);

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | AUMENTAR STOCK DESTINO
                    |--------------------------------------------------------------------------
                    */
                    $destinationStock->current_stock =
                        (float) $destinationStock->current_stock +
                        $quantity;

                    /*
                    |--------------------------------------------------------------------------
                    | ACTUALIZAR PRECIO SI VIENE INFORMADO
                    |--------------------------------------------------------------------------
                    */
                    if (
                        $item->price !== null &&
                        (float) $item->price > 0
                    ) {
                        $destinationStock->price =
                            $item->price;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | REACTIVAR STOCK
                    |--------------------------------------------------------------------------
                    */
                    $destinationStock->status = true;

                    $destinationStock->save();
                }

                /*
                |--------------------------------------------------------------------------
                | MOVIMIENTO DE ENTRADA - DESTINO
                |--------------------------------------------------------------------------
                |
                | ESTE ES EL QUE TE ESTÁ FALTANDO.
                |
                | Gracias a este registro, cuando consultes el Kardex
                | de la sucursal destino aparecerá:
                |
                | TRANSFER_IN
                |
                */
                StockMovement::create([
                    'id_company' =>
                        $idCompany,

                    'branch_id' =>
                        $transfer->destination_branch_id,

                    'product_id' =>
                        $item->product_id,

                    'user_id' =>
                        $user->id,

                    'movement_type' =>
                        'TRANSFER_IN',

                    'quantity' =>
                        $quantity,

                    'reference_type' =>
                        'product_transfer',

                    'reference_id' =>
                        $transfer->id,

                    'note' =>
                        'Entrada por transferencia aceptada desde sucursal ' .
                        $transfer->origin_branch_id .
                        '.'
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | ACTUALIZAR TRANSFERENCIA
            |--------------------------------------------------------------------------
            */
            $transfer->status = 'ACEPTADA';

            $transfer->received_by_user_id =
                $user->id;

            $transfer->received_at =
                now();

            $transfer->save();

            return $transfer;
        });

        /*
        |--------------------------------------------------------------------------
        | RECARGAR RELACIONES
        |--------------------------------------------------------------------------
        */
        $transfer->load([
            'originBranch:id,code_branch,branch_name',
            'destinationBranch:id,code_branch,branch_name',
            'user:id,name',
            'receivedByUser:id,name',
            'items.product:id,code_product,name'
        ]);

        /*
        |--------------------------------------------------------------------------
        | RESPUESTA
        |--------------------------------------------------------------------------
        */
        return response()->json([
            'message' =>
                'Transferencia aceptada correctamente. El stock y los movimientos fueron actualizados.',

            'data' =>
                $transfer
        ], 200);

    } catch (\Throwable $e) {

        return response()->json([
            'message' =>
                'Error al aceptar la transferencia.',

            'error' =>
                $e->getMessage()
        ], 500);
    }
}
    /**
     * ============================================================
     * RECHAZAR TRANSFERENCIA
     * ============================================================
     */
    public function reject(Request $request, $id)
    {
        try {

            $user = Auth::user();

            if (!$user) {

                return response()->json([
                    'message' => 'Usuario no autenticado.'
                ], 401);
            }

            $idCompany = $user->id_company;

            /*
            |--------------------------------------------------------------------------
            | VALIDAR OBSERVACIÓN
            |--------------------------------------------------------------------------
            */
            $validator = Validator::make(
                $request->all(),
                [
                    'observation' => [
                        'nullable',
                        'string',
                        'max:1000'
                    ]
                ]
            );

            if ($validator->fails()) {

                return response()->json([
                    'message' => 'Los datos enviados no son válidos.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $transfer = DB::transaction(function () use (
                $id,
                $user,
                $idCompany,
                $request
            ) {

                /*
                |--------------------------------------------------------------------------
                | BUSCAR TRANSFERENCIA
                |--------------------------------------------------------------------------
                */
                $transfer = ProductTransfer::query()
                    ->where(
                        'product_transfers.id',
                        $id
                    )
                    ->where(
                        'product_transfers.id_company',
                        $idCompany
                    )
                    ->lockForUpdate()
                    ->first();

                if (!$transfer) {

                    throw new \Exception(
                        'La transferencia no existe.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | SOLO PENDIENTE
                |--------------------------------------------------------------------------
                */
                if ($transfer->status !== 'PENDIENTE') {

                    throw new \Exception(
                        'La transferencia no puede rechazarse porque su estado actual es "' .
                            $transfer->status .
                            '".'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | VALIDAR USUARIO DESTINO
                |--------------------------------------------------------------------------
                */
                $userBelongsToDestination = DB::table('user_branches')
                    ->join(
                        'branches',
                        'branches.id',
                        '=',
                        'user_branches.branch_id'
                    )
                    ->where(
                        'user_branches.user_id',
                        $user->id
                    )
                    ->where(
                        'user_branches.branch_id',
                        $transfer->destination_branch_id
                    )
                    ->where(
                        'branches.id_company',
                        $idCompany
                    )
                    ->where(
                        'branches.status',
                        true
                    )
                    ->exists();

                if (!$userBelongsToDestination) {

                    throw new \Exception(
                        'No estás autorizado para rechazar esta transferencia.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | ACTUALIZAR
                |--------------------------------------------------------------------------
                */
                $transfer->status = 'RECHAZADA';

                $transfer->received_by_user_id =
                    $user->id;

                $transfer->observation =
                    $request->observation ??
                    $transfer->observation;

                $transfer->received_at =
                    now();

                $transfer->save();

                return $transfer;
            });

            $transfer->load([
                'originBranch:id,code_branch,branch_name',
                'destinationBranch:id,code_branch,branch_name',
                'user:id,name',
                'receivedByUser:id,name',
                'items.product:id,code_product,name'
            ]);

            return response()->json([
                'message' =>
                'Transferencia rechazada correctamente.',

                'data' => $transfer
            ], 200);
        } catch (\Throwable $e) {

            return response()->json([
                'message' =>
                'Error al rechazar la transferencia.',

                'error' =>
                $e->getMessage()
            ], 500);
        }
    }


    /**
     * ============================================================
     * CANCELAR TRANSFERENCIA
     * ============================================================
     *
     * Solamente el usuario que creó la transferencia puede
     * cancelarla y únicamente mientras esté PENDIENTE.
     */
    public function cancel(Request $request, $id)
    {
        try {

            $user = Auth::user();

            if (!$user) {

                return response()->json([
                    'message' => 'Usuario no autenticado.'
                ], 401);
            }

            $idCompany = $user->id_company;

            /*
            |--------------------------------------------------------------------------
            | VALIDAR OBSERVACIÓN
            |--------------------------------------------------------------------------
            */
            $validator = Validator::make(
                $request->all(),
                [
                    'observation' => [
                        'nullable',
                        'string',
                        'max:1000'
                    ]
                ]
            );

            if ($validator->fails()) {

                return response()->json([
                    'message' => 'Los datos enviados no son válidos.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $transfer = DB::transaction(function () use (
                $id,
                $user,
                $idCompany,
                $request
            ) {

                /*
                |--------------------------------------------------------------------------
                | BUSCAR TRANSFERENCIA
                |--------------------------------------------------------------------------
                */
                $transfer = ProductTransfer::query()
                    ->where(
                        'product_transfers.id',
                        $id
                    )
                    ->where(
                        'product_transfers.id_company',
                        $idCompany
                    )
                    ->lockForUpdate()
                    ->first();

                if (!$transfer) {

                    throw new \Exception(
                        'La transferencia no existe.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | VALIDAR CREADOR
                |--------------------------------------------------------------------------
                */
                if (
                    (int) $transfer->user_id !==
                    (int) $user->id
                ) {

                    throw new \Exception(
                        'Solo el usuario que creó la transferencia puede cancelarla.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | VALIDAR ESTADO
                |--------------------------------------------------------------------------
                */
                if ($transfer->status !== 'PENDIENTE') {

                    throw new \Exception(
                        'Solo se pueden cancelar transferencias pendientes.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | CANCELAR
                |--------------------------------------------------------------------------
                */
                $transfer->status =
                    'CANCELADA';

                if ($request->filled('observation')) {

                    $transfer->observation =
                        $request->observation;
                }

                $transfer->save();

                return $transfer;
            });

            $transfer->load([
                'originBranch:id,code_branch,branch_name',
                'destinationBranch:id,code_branch,branch_name',
                'user:id,name',
                'receivedByUser:id,name',
                'items.product:id,code_product,name'
            ]);

            return response()->json([
                'message' =>
                'Transferencia cancelada correctamente.',

                'data' => $transfer
            ], 200);
        } catch (\Throwable $e) {

            return response()->json([
                'message' =>
                'Error al cancelar la transferencia.',

                'error' =>
                $e->getMessage()
            ], 500);
        }
    }
}
