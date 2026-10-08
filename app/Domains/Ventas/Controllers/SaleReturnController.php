<?php

namespace App\Domains\Ventas\Controllers;

use App\Http\Controllers\Controller;

use App\Domains\Ventas\Models\Sale;
use App\Domains\Ventas\Models\SaleDetail;
use App\Domains\Ventas\Models\SaleReturn;
use App\Domains\Ventas\Models\SaleReturnItem;
use App\Domains\Inventario\Models\StockProduct;
use App\Domains\Clientes\Models\Client;
use App\Domains\Caja\Models\CashMovement;
use App\Domains\Caja\Models\CashRegisterSession;
use App\Domains\Inventario\Models\StockMovement;

use App\Domains\Contabilidad\Models\AccountingPeriod;
use App\Domains\Contabilidad\Services\AccountingService;



use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleReturnController extends Controller
{
    protected AccountingService $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }
    /*
    |--------------------------------------------------------------------------
    | LISTAR DEVOLUCIONES
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $user = Auth::user();

        $query = SaleReturn::query()
            ->where('id_company', $user->id_company)

            // ============================================================
            // SOLO DEVOLUCIONES DE VENTAS REALIZADAS POR EL USUARIO
            // AUTENTICADO
            // ============================================================
            ->whereHas('sale', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })

            ->with([
                'sale',
                'sale.user',
                'client',
                'branch',
                'user',
                'items.product',
                'items.saleDetail',
            ])

            ->orderByDesc('id');

        // ============================================================
        // BUSCADOR
        // ============================================================
        if ($request->filled('search')) {

            $search = trim($request->search);

            $query->where(function ($q) use ($search) {

                // Código devolución
                $q->where('code_return', 'like', "%{$search}%")

                    // Código venta
                    ->orWhereHas('sale', function ($saleQuery) use ($search) {

                        $saleQuery->where(
                            'code_sale',
                            'like',
                            "%{$search}%"
                        );
                    })

                    // Cliente
                    ->orWhereHas('client', function ($clientQuery) use ($search) {

                        $clientQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere(
                                'document_number',
                                'like',
                                "%{$search}%"
                            );
                    })

                    // Producto
                    ->orWhereHas('items.product', function ($productQuery) use ($search) {

                        $productQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere(
                                'code_product',
                                'like',
                                "%{$search}%"
                            );
                    });
            });
        }

        // ============================================================
        // CLIENTE
        // ============================================================
        if ($request->filled('client_id')) {

            $query->where(
                'client_id',
                $request->client_id
            );
        }

        // ============================================================
        // TIPO DE DEVOLUCIÓN
        // MONEY / BALANCE
        // ============================================================
        if ($request->filled('refund_type')) {

            $refundType = strtoupper(
                trim($request->refund_type)
            );

            $query->where(
                'refund_type',
                $refundType
            );
        }

        // ============================================================
        // FECHA DESDE
        // ============================================================
        if ($request->filled('date_from')) {

            $query->whereDate(
                'date',
                '>=',
                $request->date_from
            );
        }

        // ============================================================
        // FECHA HASTA
        // ============================================================
        if ($request->filled('date_to')) {

            $query->whereDate(
                'date',
                '<=',
                $request->date_to
            );
        }

        // ============================================================
        // PAGINACIÓN
        // ============================================================
        $perPage = min(
            max(
                (int) $request->get('per_page', 10),
                1
            ),
            100
        );

        return response()->json(
            $query->paginate($perPage)
        );
    }


    /*
    |--------------------------------------------------------------------------
    | MOSTRAR UNA DEVOLUCIÓN
    |--------------------------------------------------------------------------
    */

    public function show($id)
    {
        $user = Auth::user();

        $saleReturn = SaleReturn::query()
            ->where('id_company', $user->id_company)
            ->with([
                'sale',
                'client',
                'branch',
                'user',
                'cashRegister',
                'cashRegisterSession',
                'items.product',
                'items.saleDetail',
            ])
            ->findOrFail($id);

        return response()->json([
            'message' => 'Devolución encontrada.',
            'data' => $saleReturn,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | PRODUCTOS DISPONIBLES PARA DEVOLUCIÓN
    |--------------------------------------------------------------------------
    */

    public function availableItems($saleId)
    {
        $user = Auth::user();

        $sale = Sale::query()
            ->where('id_company', $user->id_company)
            ->with([
                'details.product',
            ])
            ->find($saleId);

        if (!$sale) {
            return response()->json([
                'message' => 'Venta no encontrada.',
                'data' => [],
            ], 404);
        }

        $items = [];

        foreach ($sale->details as $detail) {

            /*
            |--------------------------------------------------------------------------
            | CANTIDAD VENDIDA
            |--------------------------------------------------------------------------
            */

            $soldQuantity = (float) $detail->quantity;


            /*
            |--------------------------------------------------------------------------
            | CANTIDAD YA DEVUELTA
            |--------------------------------------------------------------------------
            */

            $returnedQuantity = SaleReturnItem::query()
                ->where('sale_detail_id', $detail->id)
                ->whereHas('saleReturn', function ($q) use ($user) {

                    $q->where('id_company', $user->id_company)
                        ->where('status', 'COMPLETED');
                })
                ->sum('quantity');

            $returnedQuantity = (float) $returnedQuantity;


            /*
            |--------------------------------------------------------------------------
            | CANTIDAD DISPONIBLE
            |--------------------------------------------------------------------------
            */

            $availableQuantity = max(
                0,
                $soldQuantity - $returnedQuantity
            );


            /*
            |--------------------------------------------------------------------------
            | SI YA SE DEVOLVIÓ TODO, NO SE MUESTRA
            |--------------------------------------------------------------------------
            */

            if ($availableQuantity <= 0) {
                continue;
            }


            /*
            |--------------------------------------------------------------------------
            | PRODUCTO
            |--------------------------------------------------------------------------
            */

            $product = $detail->product;


            /*
            |--------------------------------------------------------------------------
            | AGREGAR ITEM
            |--------------------------------------------------------------------------
            */

            $items[] = [

                'sale_detail_id' => $detail->id,

                'product_id' => $detail->product_id,

                'product' => $product,

                'quantity' => $soldQuantity,

                'quantity_sold' => $soldQuantity,

                'returned_quantity' => $returnedQuantity,

                'quantity_returned' => $returnedQuantity,

                'available_quantity' => $availableQuantity,

                'quantity_available' => $availableQuantity,

                'unit_price' => (float) $detail->unit_price,

                'subtotal' => (float) $detail->subtotal,

                'discount' => (float) ($detail->discount ?? 0),

                'iva' => (float) ($detail->iva ?? 0),

                'total' => (float) $detail->total,
            ];
        }


        /*
        |--------------------------------------------------------------------------
        | RESPUESTA
        |--------------------------------------------------------------------------
        |
        | IMPORTANTE:
        | Vue debe leer response.data
        |
        */

        return response()->json([

            'message' => 'Productos disponibles para devolución.',

            'sale' => [
                'id' => $sale->id,
                'code_sale' => $sale->code_sale ?? null,
            ],

            'data' => $items,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | DEVOLUCIONES DE UNA VENTA
    |--------------------------------------------------------------------------
    */

    public function bySale($saleId)
    {
        $user = Auth::user();

        $sale = Sale::query()
            ->where('id_company', $user->id_company)
            ->findOrFail($saleId);

        $returns = SaleReturn::query()
            ->where('id_company', $user->id_company)
            ->where('sale_id', $sale->id)
            ->with([
                'client',
                'branch',
                'user',
                'items.product',
                'items.saleDetail',
            ])
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'message' => 'Devoluciones de la venta.',
            'data' => $returns,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | REGISTRAR DEVOLUCIÓN
    |--------------------------------------------------------------------------
    */

    public function store(Request $request, $saleId)
    {
        $user = Auth::user();

        /*
    |--------------------------------------------------------------------------
    | VALIDACIÓN
    |--------------------------------------------------------------------------
    */

        $validated = $request->validate([
            'refund_type' => [
                'required',
                'in:MONEY,BALANCE,dinero,saldo',
            ],

            'refund_method' => [
                'nullable',
                'in:CASH,CARD,QR,TRANSFER,OTHER,EFECTIVO,TARJETA',
            ],

            'reason' => [
                'required',
                'string',
                'max:500',
            ],

            'items' => [
                'required',
                'array',
                'min:1',
            ],

            'items.*.sale_detail_id' => [
                'required',
                'integer',
            ],

            'items.*.quantity' => [
                'required',
                'numeric',
                'gt:0',
            ],
        ]);


        /*
    |--------------------------------------------------------------------------
    | NORMALIZAR TIPO
    |--------------------------------------------------------------------------
    */

        $refundType = strtoupper(
            trim($validated['refund_type'])
        );

        if ($refundType === 'DINERO') {
            $refundType = 'MONEY';
        }

        if ($refundType === 'SALDO') {
            $refundType = 'BALANCE';
        }


        /*
    |--------------------------------------------------------------------------
    | NORMALIZAR MÉTODO
    |--------------------------------------------------------------------------
    */

        $refundMethod = $validated['refund_method'] ?? null;

        if ($refundMethod) {

            $refundMethod = strtoupper(
                trim($refundMethod)
            );

            if ($refundMethod === 'EFECTIVO') {
                $refundMethod = 'CASH';
            }

            if ($refundMethod === 'TARJETA') {
                $refundMethod = 'CARD';
            }
        }


        /*
    |--------------------------------------------------------------------------
    | MONEY REQUIERE MÉTODO
    |--------------------------------------------------------------------------
    */

        if (
            $refundType === 'MONEY'
            && !$refundMethod
        ) {

            throw ValidationException::withMessages([
                'refund_method' =>
                'Debe seleccionar el método de devolución del dinero.',
            ]);
        }


        /*
    |--------------------------------------------------------------------------
    | BALANCE NO USA MÉTODO
    |--------------------------------------------------------------------------
    */

        if ($refundType === 'BALANCE') {
            $refundMethod = null;
        }


        DB::beginTransaction();

        try {

            /*
        |--------------------------------------------------------------------------
        | OBTENER VENTA
        |--------------------------------------------------------------------------
        */

            $sale = Sale::query()
                ->where('id_company', $user->id_company)
                ->with([
                    'details',
                    'client',
                ])
                ->lockForUpdate()
                ->find($saleId);

            if (!$sale) {

                throw ValidationException::withMessages([
                    'sale_id' => 'La venta no existe.',
                ]);
            }


            /*
        |--------------------------------------------------------------------------
        | SESIÓN DE CAJA
        |--------------------------------------------------------------------------
        |
        | SOLO MONEY + CASH utiliza caja física.
        |--------------------------------------------------------------------------
        */

           /*
|--------------------------------------------------------------------------
| SESIÓN DE CAJA
|--------------------------------------------------------------------------
|
| Toda devolución MONEY se registra en la sesión de caja actual.
|
| CASH     -> registra sesión y afecta efectivo físico.
| QR       -> registra sesión, NO afecta efectivo físico.
| CARD     -> registra sesión, NO afecta efectivo físico.
| TRANSFER -> registra sesión, NO afecta efectivo físico.
| OTHER    -> registra sesión, NO afecta efectivo físico.
|
| BALANCE  -> no utiliza sesión de caja.
|--------------------------------------------------------------------------
*/

$cashRegisterSession = null;

if ($refundType === 'MONEY') {

    $cashRegisterSession = CashRegisterSession::query()
        ->where('id_company', $user->id_company)
        ->where('user_id', $user->id)
        ->where('status', 'OPEN')
        ->whereHas('cashRegister', function ($q) use ($sale) {

            $q->where(
                'branch_id',
                $sale->branch_id
            );

        })
        ->lockForUpdate()
        ->first();

    if (!$cashRegisterSession) {

        throw ValidationException::withMessages([
            'cash_register_session' =>
            'El usuario no tiene una sesión de caja abierta para esta sucursal.',
        ]);
    }
}


            /*
        |--------------------------------------------------------------------------
        | GENERAR CÓDIGO
        |--------------------------------------------------------------------------
        */

            $lastReturn = SaleReturn::query()
                ->where('id_company', $user->id_company)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            $nextNumber = 1;

            if (
                $lastReturn
                && $lastReturn->code_return
            ) {

                if (
                    preg_match(
                        '/DEV-(\d+)/',
                        $lastReturn->code_return,
                        $matches
                    )
                ) {

                    $nextNumber =
                        ((int) $matches[1]) + 1;
                }
            }

            $codeReturn =
                'DEV-' .
                str_pad(
                    $nextNumber,
                    6,
                    '0',
                    STR_PAD_LEFT
                );


            /*
        |--------------------------------------------------------------------------
        | PREPARAR ITEMS
        |--------------------------------------------------------------------------
        */

            $processedItems = [];

            $totalReturn = 0;


            foreach ($validated['items'] as $itemData) {

                $saleDetailId =
                    (int) $itemData['sale_detail_id'];

                $returnQuantity =
                    (float) $itemData['quantity'];


                /*
            |--------------------------------------------------------------------------
            | DETALLE DE VENTA
            |--------------------------------------------------------------------------
            */

                $saleDetail = SaleDetail::query()
                    ->where('id', $saleDetailId)
                    ->where('sale_id', $sale->id)
                    ->with('product')
                    ->lockForUpdate()
                    ->first();

                if (!$saleDetail) {

                    throw ValidationException::withMessages([
                        'items' =>
                        "El detalle de venta {$saleDetailId} no pertenece a esta venta.",
                    ]);
                }


                /*
            |--------------------------------------------------------------------------
            | CANTIDAD VENDIDA
            |--------------------------------------------------------------------------
            */

                $soldQuantity =
                    (float) $saleDetail->quantity;


                /*
            |--------------------------------------------------------------------------
            | CANTIDAD YA DEVUELTA
            |--------------------------------------------------------------------------
            */

                $alreadyReturned =
                    SaleReturnItem::query()
                    ->where(
                        'sale_detail_id',
                        $saleDetail->id
                    )
                    ->whereHas(
                        'saleReturn',
                        function ($q) use ($user) {

                            $q->where(
                                'id_company',
                                $user->id_company
                            )
                                ->where(
                                    'status',
                                    'COMPLETED'
                                );
                        }
                    )
                    ->sum('quantity');

                $alreadyReturned =
                    (float) $alreadyReturned;


                /*
            |--------------------------------------------------------------------------
            | DISPONIBLE
            |--------------------------------------------------------------------------
            */

                $availableQuantity =
                    max(
                        0,
                        $soldQuantity - $alreadyReturned
                    );


                if (
                    $returnQuantity >
                    $availableQuantity
                ) {

                    throw ValidationException::withMessages([
                        'items' =>
                        "La cantidad a devolver del producto {$saleDetail->product_id} supera la cantidad disponible. Disponible: {$availableQuantity}.",
                    ]);
                }


                /*
            |--------------------------------------------------------------------------
            | PRODUCTO
            |--------------------------------------------------------------------------
            */

                $product =
                    $saleDetail->product;

                if (!$product) {

                    throw ValidationException::withMessages([
                        'items' =>
                        "No se encontró el producto del detalle {$saleDetail->id}.",
                    ]);
                }


                /*
            |--------------------------------------------------------------------------
            | PRECIO
            |--------------------------------------------------------------------------
            */

                $unitPrice =
                    (float) (
                        $saleDetail->unit_price
                        ?? $saleDetail->precio_unitario
                        ?? $saleDetail->price
                        ?? 0
                    );


                /*
            |--------------------------------------------------------------------------
            | SUBTOTAL
            |--------------------------------------------------------------------------
            */

                $subtotal =
                    $unitPrice *
                    $returnQuantity;


                /*
            |--------------------------------------------------------------------------
            | DESCUENTO
            |--------------------------------------------------------------------------
            */

                $originalQuantity =
                    max(
                        1,
                        $soldQuantity
                    );

                $discountOriginal =
                    (float) (
                        $saleDetail->discount
                        ?? 0
                    );

                $discountReturn =
                    (
                        $discountOriginal /
                        $originalQuantity
                    ) *
                    $returnQuantity;


                /*
            |--------------------------------------------------------------------------
            | IVA
            |--------------------------------------------------------------------------
            */

                $ivaOriginal =
                    (float) (
                        $saleDetail->iva
                        ?? 0
                    );

                $ivaReturn =
                    (
                        $ivaOriginal /
                        $originalQuantity
                    ) *
                    $returnQuantity;


                /*
            |--------------------------------------------------------------------------
            | TOTAL
            |--------------------------------------------------------------------------
            */

                $itemTotal =
                    max(
                        0,
                        $subtotal
                            - $discountReturn
                            + $ivaReturn
                    );

                $totalReturn +=
                    $itemTotal;


                /*
            |--------------------------------------------------------------------------
            | GUARDAR ITEM PROCESADO
            |--------------------------------------------------------------------------
            */

                $processedItems[] = [

                    'sale_detail' =>
                    $saleDetail,

                    'product' =>
                    $product,

                    'quantity' =>
                    $returnQuantity,

                    'unit_price' =>
                    $unitPrice,

                    'subtotal' =>
                    $subtotal,

                    'discount' =>
                    $discountReturn,

                    'iva' =>
                    $ivaReturn,

                    'total' =>
                    $itemTotal,
                ];
            }


            /*
        |--------------------------------------------------------------------------
        | REDONDEAR TOTAL
        |--------------------------------------------------------------------------
        */

            $totalReturn =
                round(
                    $totalReturn,
                    2
                );


            /*
        |--------------------------------------------------------------------------
        | CREAR DEVOLUCIÓN
        |--------------------------------------------------------------------------
        */

            $saleReturn = SaleReturn::create([

                'code_return' =>
                $codeReturn,

                'id_company' =>
                $user->id_company,

                'sale_id' =>
                $sale->id,

                'client_id' =>
                $sale->client_id,

                'branch_id' =>
                $sale->branch_id,

                'user_id' =>
                $user->id,

                'cash_register_id' =>
                $cashRegisterSession?->cash_register_id,

                'cash_register_session_id' =>
                $cashRegisterSession?->id,

                'refund_type' =>
                $refundType,

                'refund_method' =>
                $refundMethod,

                'reason' =>
                trim($validated['reason']),

                'total_amount' =>
                $totalReturn,

                'status' =>
                'COMPLETED',

                'date' =>
                now(),
            ]);


            /*
        |--------------------------------------------------------------------------
        | CREAR ITEMS + RESTAURAR STOCK
        |--------------------------------------------------------------------------
        */

            foreach ($processedItems as $processed) {

                $saleDetail =
                    $processed['sale_detail'];

                $product =
                    $processed['product'];

                $quantity =
                    $processed['quantity'];


                /*
            |--------------------------------------------------------------------------
            | ITEM DEVOLUCIÓN
            |--------------------------------------------------------------------------
            */

                SaleReturnItem::create([

                    'sale_return_id' =>
                    $saleReturn->id,

                    'sale_detail_id' =>
                    $saleDetail->id,

                    'product_id' =>
                    $product->id,

                    'quantity' =>
                    $quantity,

                    'unit_price' =>
                    $processed['unit_price'],

                    'subtotal' =>
                    $processed['subtotal'],

                    'discount' =>
                    $processed['discount'],

                    'iva' =>
                    $processed['iva'],

                    'total' =>
                    $processed['total'],

                    'reason' =>
                    trim($validated['reason']),
                ]);


                /*
            |--------------------------------------------------------------------------
            | ACTUALIZAR DETALLE
            |--------------------------------------------------------------------------
            */

                $currentReturned =
                    (float) (
                        $saleDetail->returned_quantity
                        ?? 0
                    );

                $newReturned =
                    $currentReturned +
                    $quantity;

                $saleDetail->returned_quantity =
                    $newReturned;

                if (
                    array_key_exists(
                        'returned',
                        $saleDetail->getAttributes()
                    )
                ) {

                    $saleDetail->returned =
                        $newReturned >=
                        (float) $saleDetail->quantity;
                }

                $saleDetail->save();


                /*
            |--------------------------------------------------------------------------
            | RESTAURAR STOCK
            |--------------------------------------------------------------------------
            */

                $stock = StockProduct::query()
                    ->where(
                        'id_company',
                        $user->id_company
                    )
                    ->where(
                        'branch_id',
                        $sale->branch_id
                    )
                    ->where(
                        'product_id',
                        $product->id
                    )
                    ->lockForUpdate()
                    ->first();

                if (!$stock) {

                    throw ValidationException::withMessages([
                        'stock' =>
                        "No existe stock para el producto {$product->id} en la sucursal {$sale->branch_id}.",
                    ]);
                }


                $stock->current_stock =
                    (float) $stock->current_stock +
                    $quantity;

                $stock->save();


                /*
            |--------------------------------------------------------------------------
            | KARDEX
            |--------------------------------------------------------------------------
            */

                StockMovement::create([

                    'id_company' =>
                    $user->id_company,

                    'branch_id' =>
                    $sale->branch_id,

                    'product_id' =>
                    $product->id,

                    'user_id' =>
                    $user->id,

                    'movement_type' =>
                    'RETURN',

                    'quantity' =>
                    $quantity,

                    'reference_type' =>
                    'sale_return',

                    'reference_id' =>
                    $saleReturn->id,

                    'note' =>
                    "Devolución {$codeReturn} de la venta {$sale->code_sale}",
                ]);
            }


            /*
        |--------------------------------------------------------------------------
        | BALANCE DEL CLIENTE
        |--------------------------------------------------------------------------
        |
        | BALANCE:
        |
        | NO entrega efectivo.
        | NO afecta caja.
        |
        | Aumenta el saldo a favor del cliente.
        |--------------------------------------------------------------------------
        */

            if ($refundType === 'BALANCE') {

                if (!$sale->client_id) {

                    throw ValidationException::withMessages([
                        'client_id' =>
                        'La venta no tiene un cliente asociado para registrar el saldo.',
                    ]);
                }

                $client = Client::query()
                    ->where(
                        'id_company',
                        $user->id_company
                    )
                    ->lockForUpdate()
                    ->find($sale->client_id);

                if (!$client) {

                    throw ValidationException::withMessages([
                        'client_id' =>
                        'No se encontró el cliente de la venta.',
                    ]);
                }


                $client->balance =
                    (float) (
                        $client->balance ?? 0
                    ) +
                    $totalReturn;

                $client->save();
            }


            /*
        |--------------------------------------------------------------------------
        | DEVOLUCIÓN EFECTIVO
        |--------------------------------------------------------------------------
        |
        | MONEY + CASH
        |
        | Sale dinero de la caja.
        |--------------------------------------------------------------------------
        */

            if (
                $refundType === 'MONEY'
                && $refundMethod === 'CASH'
            ) {

                CashMovement::create([

                    'id_company' =>
                    $user->id_company,

                    'branch_id' =>
                    $sale->branch_id,

                    'cash_register_id' =>
                    $cashRegisterSession->cash_register_id,

                    'cash_register_session_id' =>
                    $cashRegisterSession->id,

                    'user_id' =>
                    $user->id,

                    'type' =>
                    'EGRESO',

                    'movement' =>
                    'OUT',

                    'amount' =>
                    $totalReturn,

                    'payment_method' =>
                    'CASH',

                    'description' =>
                    "Devolución {$codeReturn} de la venta {$sale->code_sale}",
                    'sale_return_id'=>$saleReturn->id,
                ]);
            }


            /*
        |--------------------------------------------------------------------------
        | CONTABILIDAD
        |--------------------------------------------------------------------------
        */

            $accountingPeriod =
                AccountingPeriod::query()
                ->where(
                    'id_company',
                    $user->id_company
                )
                ->where(
                    'status',
                    'OPEN'
                )
                ->first();

            if (!$accountingPeriod) {

                throw ValidationException::withMessages([
                    'accounting_period' =>
                    'No existe un período contable abierto para registrar la devolución.',
                ]);
            }


            /*
        |--------------------------------------------------------------------------
        | CUENTA DEVOLUCION_VENTAS
        |--------------------------------------------------------------------------
        */

            $returnAccount =
                $this->accountingService->getMappedAccount(
                    $user->id_company,
                    'DEVOLUCION_VENTAS'
                );

            if (!$returnAccount) {

                throw ValidationException::withMessages([
                    'accounting_account' =>
                    'No existe una cuenta configurada para DEVOLUCION_VENTAS.',
                ]);
            }


            /*
        |--------------------------------------------------------------------------
        | CUENTA CONTRAPARTIDA
        |--------------------------------------------------------------------------
        */

            if ($refundType === 'BALANCE') {

                /*
            |--------------------------------------------------------------
            | BALANCE
            |--------------------------------------------------------------
            |
            | DEBE  DEVOLUCION_VENTAS
            | HABER CUENTAS_POR_COBRAR
            |
            */

                $counterAccount =
                    $this->accountingService->getMappedAccount(
                        $user->id_company,
                        'CUENTA_POR_COBRAR'
                    );
            } else {

                /*
            |--------------------------------------------------------------
            | MONEY
            |--------------------------------------------------------------
            |
            | CASH  -> CAJA
            | CARD  -> CUENTA TARJETA
            | QR    -> CUENTA QR
            | TRANSFER -> BANCO
            | OTHER -> CUENTA OTHER
            |
            */

                $counterAccount =
                    $this->accountingService->getPaymentAccount(
                        $user->id_company,
                        $refundMethod,
                        $cashRegisterSession
                    );
            }


            if (!$counterAccount) {

                throw ValidationException::withMessages([
                    'accounting_account' =>
                    'No se encontró la cuenta contable de contrapartida para la devolución.',
                ]);
            }


            /*
        |--------------------------------------------------------------------------
        | CREAR ASIENTO CONTABLE
        |--------------------------------------------------------------------------
        */

            $this->accountingService->createEntry(

                [

                    'id_company' =>
                    $user->id_company,

                    'branch_id' =>
                    $sale->branch_id,

                    'accounting_period_id' =>
                    $accountingPeriod->id,

                    'date' =>
                    now(),

                    'type' =>
                    'DEVOLUCION',

                    'description' =>
                    "Devolución {$codeReturn} de la venta {$sale->code_sale}",

                    'reference_type' =>
                    SaleReturn::class,

                    'reference_id' =>
                    $saleReturn->id,

                    'user_id' =>
                    $user->id,

                    'status' =>
                    'POSTED',
               
                ],

                [

                    /*
                |----------------------------------------------------------
                | DEBE
                |----------------------------------------------------------
                */

                    [

                        'accounting_account_id' =>
                        $returnAccount->id,

                        'description' =>
                        "Devolución {$codeReturn}",

                        'debit' =>
                        $totalReturn,

                        'credit' =>
                        0,
                    ],

                    /*
                |----------------------------------------------------------
                | HABER
                |----------------------------------------------------------
                */

                    [

                        'accounting_account_id' =>
                        $counterAccount->id,

                        'description' =>
                        "Contrapartida devolución {$codeReturn}",

                        'debit' =>
                        0,

                        'credit' =>
                        $totalReturn,
                    ],
                ]
            );


            /*
        |--------------------------------------------------------------------------
        | CONFIRMAR TRANSACCIÓN
        |--------------------------------------------------------------------------
        */

            DB::commit();


            /*
        |--------------------------------------------------------------------------
        | RESPUESTA
        |--------------------------------------------------------------------------
        */

            $saleReturn->load([
                'sale',
                'client',
                'branch',
                'user',
                'cashRegister',
                'cashRegisterSession',
                'items.product',
                'items.saleDetail',
            ]);

            return response()->json([

                'message' =>
                'Devolución registrada correctamente.',

                'data' =>
                $saleReturn,

            ], 201);
        } catch (\Throwable $e) {

            DB::rollBack();

            throw $e;
        }
    }
}
