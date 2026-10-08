<?php

namespace App\Domains\Ventas\Controllers;


use App\Http\Controllers\Controller;

use App\Domains\Ventas\Models\Sale;
use App\Domains\Ventas\Models\SaleDetail;
use App\Domains\Ventas\Models\SalePayment;

use App\Domains\Inventario\Models\Product;
use App\Domains\Inventario\Models\StockProduct;
use App\Domains\Inventario\Models\StockMovement;

use App\Domains\Clientes\Models\Client;

use App\Domains\Caja\Models\CashMovement;
use App\Domains\Caja\Models\CashRegisterSession;
use App\Domains\Caja\Models\CashCategory;

use App\Domains\Contabilidad\Models\AccountingPeriod;


use App\Domains\Contabilidad\Services\AccountingService;
use App\Domains\Ventas\Services\SalePdfService;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SaleController extends Controller
{
    protected AccountingService $accountingService;
    protected SalePdfService $salePdfService;

    public function __construct(
        AccountingService $accountingService,
        SalePdfService $salePdfService
    ) {
        $this->accountingService = $accountingService;
        $this->salePdfService = $salePdfService;
    }
    /**
     * ============================================================
     * REGISTRAR VENTA
     * ============================================================
     */
    public function store(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | 1. VALIDACIÓN
        |--------------------------------------------------------------------------
        */

        $validator = Validator::make($request->all(), [

            'accion' => [
                'required',
                'string',
                'in:venta'
            ],

            'client_id' => [
                'required',
                'integer',
                'exists:clients,id'
            ],

            'branch_id' => [
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
                'required',
                'numeric',
                'gte:0'
            ],

            'subtotal' => [
                'required',
                'numeric',
                'gte:0'
            ],

            'discount' => [
                'nullable',
                'numeric',
                'gte:0'
            ],

            'total' => [
                'required',
                'numeric',
                'gte:0'
            ],

            'usedBalance' => [
                'nullable',
                'numeric',
                'gte:0'
            ],

            'payments' => [
                'nullable',
                'array'
            ],

            'payments.*.payment_method' => [
                'required',
                'string',
                'in:CASH,CARD,TRANSFER,QR,CHECK,BALANCE,OTHER'
            ],

            'payments.*.amount' => [
                'required',
                'numeric',
                'gt:0'
            ],

            'payments.*.reference' => [
                'nullable',
                'string',
                'max:255'
            ],

            'payments.*.observation' => [
                'nullable',
                'string'
            ],
        ]);

        if ($validator->fails()) {

            return response()->json([
                'success' => false,
                'message' => 'Los datos enviados no son válidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | 2. USUARIO
        |--------------------------------------------------------------------------
        */

        $user = Auth::user();

        if (!$user) {

            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | 3. EMPRESA
        |--------------------------------------------------------------------------
        */

        $idCompany = $user->id_company;

        if (!$idCompany) {

            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una empresa asignada.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | 4. DATOS
        |--------------------------------------------------------------------------
        */

        $clientId = (int) $request->client_id;

        $branchId = (int) $request->branch_id;

        $subtotal = round(
            (float) $request->subtotal,
            2
        );

        $discount = round(
            (float) ($request->discount ?? 0),
            2
        );

        $total = round(
            (float) $request->total,
            2
        );

        $usedBalance = round(
            (float) ($request->usedBalance ?? 0),
            2
        );

        $payments = $request->input(
            'payments',
            []
        );

        /*
        |--------------------------------------------------------------------------
        | 5. VALIDAR DESCUENTO
        |--------------------------------------------------------------------------
        */

        if ($discount > $subtotal) {

            return response()->json([
                'success' => false,
                'message' => 'El descuento no puede ser mayor al subtotal.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | 6. VALIDAR SALDO
        |--------------------------------------------------------------------------
        */

        if ($usedBalance > $total) {

            return response()->json([
                'success' => false,
                'message' => 'El saldo utilizado no puede ser mayor al total de la venta.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | 7. TOTAL FINAL
        |--------------------------------------------------------------------------
        */

        $totalFinal = round(
            max($total - $usedBalance, 0),
            2
        );

        /*
        |--------------------------------------------------------------------------
        | 8. VALIDAR PAGOS
        |--------------------------------------------------------------------------
        */

        $totalPayments = 0;

        foreach ($payments as $payment) {

            $totalPayments += round(
                (float) $payment['amount'],
                2
            );
        }

        $totalPayments = round(
            $totalPayments,
            2
        );

        /*
        |--------------------------------------------------------------------------
        | SI HAY SALDO PENDIENTE DEBE EXISTIR PAGO
        |--------------------------------------------------------------------------
        */

        if (
            $totalFinal > 0 &&
            count($payments) === 0
        ) {

            return response()->json([
                'success' => false,
                'message' => 'Debe registrar un método de pago para cubrir el importe restante.',
                'data' => [
                    'total' => $total,
                    'usedBalance' => $usedBalance,
                    'totalFinal' => $totalFinal,
                ],
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | PAGOS = TOTAL FINAL
        |--------------------------------------------------------------------------
        */

        if (
            abs($totalPayments - $totalFinal) > 0.01
        ) {

            return response()->json([
                'success' => false,
                'message' => 'La suma de los pagos no coincide con el total pendiente.',
                'data' => [
                    'total' => $total,
                    'usedBalance' => $usedBalance,
                    'totalFinal' => $totalFinal,
                    'totalPayments' => $totalPayments,
                    'faltante' => round(
                        max($totalFinal - $totalPayments, 0),
                        2
                    ),
                ],
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | BALANCE NO ES PAYMENT
        |--------------------------------------------------------------------------
        */

        foreach ($payments as $payment) {

            if (
                strtoupper(
                    $payment['payment_method']
                ) === 'BALANCE'
            ) {

                return response()->json([
                    'success' => false,
                    'message' => 'El saldo del cliente debe utilizarse mediante usedBalance y no mediante payments.',
                ], 422);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 9. TRANSACCIÓN
        |--------------------------------------------------------------------------
        */

        try {

            $result = DB::transaction(function () use (
                $request,
                $user,
                $idCompany,
                $clientId,
                $branchId,
                $subtotal,
                $discount,
                $total,
                $usedBalance,
                $totalFinal,
                $payments
            ) {

                /*
                |--------------------------------------------------------------------------
                | 10. VALIDAR SUCURSAL
                |--------------------------------------------------------------------------
                */

                $branch = DB::table('branches')
                    ->where('id', $branchId)
                    ->where('id_company', $idCompany)
                    ->where('status', true)
                    ->first();

                if (!$branch) {

                    throw new \Exception(
                        'La sucursal seleccionada no existe, está inactiva o no pertenece a la empresa.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | 11. VALIDAR CLIENTE
                |--------------------------------------------------------------------------
                */

                $client = Client::query()
                    ->where('id', $clientId)
                    ->where('id_company', $idCompany)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->first();

                if (!$client) {

                    throw new \Exception(
                        'El cliente no existe o no pertenece a la empresa.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | 12. SALDO CLIENTE
                |--------------------------------------------------------------------------
                */

                $clientBalance = round(
                    (float) ($client->balance ?? 0),
                    2
                );

                if ($usedBalance > $clientBalance) {

                    throw new \Exception(
                        'Saldo insuficiente. ' .
                            'Disponible: ' . $clientBalance .
                            '. Solicitado: ' . $usedBalance . '.'
                    );
                }

                /*
|--------------------------------------------------------------------------
| 13. DETERMINAR SI NECESITAMOS CAJA
|--------------------------------------------------------------------------
*/

                $requiresCashSession = false;

                foreach ($payments as $payment) {

                    if (
                        strtoupper($payment['payment_method']) === 'CASH'
                    ) {

                        $requiresCashSession = true;

                        break;
                    }
                }


                /*
|--------------------------------------------------------------------------
| 14. BUSCAR SESIÓN DE CAJA ABIERTA
|--------------------------------------------------------------------------
|
| La sesión se busca siempre para poder asociar también:
|
| CASH
| CARD
| TRANSFER
| QR
| CHECK
| OTHER
|
| Pero solamente CASH obliga a tener una sesión abierta.
|
*/

                $cashSession = CashRegisterSession::query()
                    ->with('cashRegister')
                    ->where('id_company', $idCompany)
                    ->where('user_id', $user->id)
                    ->where('status', 'OPEN')
                    ->lockForUpdate()
                    ->first();


                /*
|--------------------------------------------------------------------------
| SI ES EFECTIVO, LA CAJA ES OBLIGATORIA
|--------------------------------------------------------------------------
*/

                if (
                    $requiresCashSession &&
                    !$cashSession
                ) {

                    throw new \Exception(
                        'No puede registrar la venta en efectivo porque no tiene una caja abierta.'
                    );
                }


                /*
|--------------------------------------------------------------------------
| VALIDAR CAJA SI EXISTE SESIÓN
|--------------------------------------------------------------------------
*/

                if ($cashSession) {

                    if (!$cashSession->cashRegister) {

                        throw new \Exception(
                            'La sesión de caja no tiene una caja asociada.'
                        );
                    }


                    /*
    |--------------------------------------------------------------------------
    | VALIDAR EMPRESA
    |--------------------------------------------------------------------------
    */

                    if (
                        (string) $cashSession->cashRegister->id_company !==
                        (string) $idCompany
                    ) {

                        throw new \Exception(
                            'La caja abierta no pertenece a la empresa actual.'
                        );
                    }


                    /*
    |--------------------------------------------------------------------------
    | VALIDAR SUCURSAL
    |--------------------------------------------------------------------------
    */

                    if (
                        isset($cashSession->cashRegister->branch_id) &&
                        (int) $cashSession->cashRegister->branch_id !==
                        (int) $branchId
                    ) {

                        /*
        |--------------------------------------------------------------------------
        | Si hay CASH no podemos continuar.
        |--------------------------------------------------------------------------
        */

                        if ($requiresCashSession) {

                            throw new \Exception(
                                'La caja abierta no pertenece a la sucursal seleccionada.'
                            );
                        }

                        /*
        |--------------------------------------------------------------------------
        | Si NO hay CASH:
        |
        | No asociamos los pagos a una caja de otra sucursal.
        |--------------------------------------------------------------------------
        */

                        $cashSession = null;
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | 15. VALIDAR PRODUCTOS Y STOCK
                |--------------------------------------------------------------------------
                */

                $validatedItems = [];

                foreach ($request->items as $item) {

                    $productId = (int) $item['product_id'];

                    /*
                    |--------------------------------------------------------------------------
                    | PRODUCTO
                    |--------------------------------------------------------------------------
                    */

                    $product = Product::query()
                        ->where('id', $productId)
                        ->where('id_company', $idCompany)
                        ->where('status', true)
                        ->lockForUpdate()
                        ->first();

                    if (!$product) {

                        throw new \Exception(
                            'El producto con ID ' .
                                $productId .
                                ' no existe, está inactivo o no pertenece a la empresa.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | CANTIDAD
                    |--------------------------------------------------------------------------
                    */

                    $quantity = round(
                        (float) $item['quantity'],
                        2
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | PRECIO
                    |--------------------------------------------------------------------------
                    */

                    $unitPrice = round(
                        (float) $item['price'],
                        2
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | STOCK
                    |--------------------------------------------------------------------------
                    */

                    $stockProduct = StockProduct::query()
                        ->where('id_company', $idCompany)
                        ->where('branch_id', $branchId)
                        ->where('product_id', $productId)
                        ->where('status', true)
                        ->lockForUpdate()
                        ->first();

                    if (!$stockProduct) {

                        throw new \Exception(
                            'No existe stock configurado para el producto ' .
                                $product->name .
                                ' en la sucursal seleccionada.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | STOCK ACTUAL
                    |--------------------------------------------------------------------------
                    */

                    $currentStock = round(
                        (float) $stockProduct->current_stock,
                        2
                    );

                    if ($currentStock < $quantity) {

                        throw new \Exception(
                            'Stock insuficiente para ' .
                                $product->name .
                                '. Disponible: ' .
                                $currentStock .
                                '. Solicitado: ' .
                                $quantity .
                                '.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | SUBTOTAL
                    |--------------------------------------------------------------------------
                    */

                    $detailSubtotal = round(
                        $quantity * $unitPrice,
                        2
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | GUARDAR ITEM
                    |--------------------------------------------------------------------------
                    */

                   $costUnit = round((float) $product->purchase_price, 2);

                    $costTotal = round(
                        $quantity * $costUnit,
                        2
                    );

                    $validatedItems[] = [
                        'product' => $product,
                        'stock' => $stockProduct,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'subtotal' => $detailSubtotal,

                        // Costo contable
                        'cost_unit' => $costUnit,
                        'cost_total' => $costTotal,
                    ];
                }

                /*
                |--------------------------------------------------------------------------
                | 16. CORRELATIVO VENTA
                |--------------------------------------------------------------------------
                */

                $lastSale = Sale::query()
                    ->where('id_company', $idCompany)
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();

                if ($lastSale) {

                    preg_match(
                        '/(\d+)$/',
                        $lastSale->code_sale,
                        $matches
                    );

                    $saleNumber =
                        isset($matches[1])
                        ? ((int) $matches[1]) + 1
                        : 1;
                } else {

                    $saleNumber = 1;
                }

                $codeSale =
                    'VEN-' .
                    str_pad(
                        $saleNumber,
                        6,
                        '0',
                        STR_PAD_LEFT
                    );

                /*
                |--------------------------------------------------------------------------
                | 17. CORRELATIVO COMPROBANTE
                |--------------------------------------------------------------------------
                */

                $lastReceipt = Sale::query()
                    ->where('id_company', $idCompany)
                    ->whereNotNull('receipt_number')
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();

                if ($lastReceipt) {

                    preg_match(
                        '/(\d+)$/',
                        $lastReceipt->receipt_number,
                        $matches
                    );

                    $receiptNumber =
                        isset($matches[1])
                        ? ((int) $matches[1]) + 1
                        : 1;
                } else {

                    $receiptNumber = 1;
                }

                $receiptNumber = str_pad(
                    $receiptNumber,
                    6,
                    '0',
                    STR_PAD_LEFT
                );

                /*
                |--------------------------------------------------------------------------
                | 18. CREAR VENTA
                |--------------------------------------------------------------------------
                */

                $sale = Sale::create([

                    'code_sale' => $codeSale,

                    'id_company' => $idCompany,

                    'receipt_number' => $receiptNumber,

                    'date' => now(),

                    'discount' => $discount,

                    'total' => $total,

                    'pdf_url' => null,

                    'user_id' => $user->id,

                    'client_id' => $clientId,

                    'branch_id' => $branchId,

                ]);

                /*
|--------------------------------------------------------------------------
| 19. PAGOS
|--------------------------------------------------------------------------
*/

                $createdPayments = [];

                /*
|--------------------------------------------------------------------------
| 19. PAGOS
|--------------------------------------------------------------------------
*/


                foreach ($payments as $paymentData) {

                    $paymentMethod = strtoupper(
                        $paymentData['payment_method']
                    );

                    $amount = round(
                        (float) $paymentData['amount'],
                        2
                    );

                    /*
    |--------------------------------------------------------------------------
    | VALIDAR CASH
    |--------------------------------------------------------------------------
    */

                    if (
                        $paymentMethod === 'CASH' &&
                        !$cashSession
                    ) {

                        throw new \Exception(
                            'No existe una sesión de caja abierta para registrar el pago en efectivo.'
                        );
                    }

                    /*
    |--------------------------------------------------------------------------
    | CREAR SALE PAYMENT
    |--------------------------------------------------------------------------
    */

                    $salePaymentData = [

                        'id_company' =>
                        $idCompany,

                        'sale_id' =>
                        $sale->id,

                        'payment_method' =>
                        $paymentMethod,

                        'amount' =>
                        $amount,

                        'reference' =>
                        $paymentData['reference'] ?? null,

                        'observation' =>
                        $paymentData['observation'] ?? null,
                    ];

                    /*
    |--------------------------------------------------------------------------
    | ASOCIAR SESIÓN DE CAJA
    |--------------------------------------------------------------------------
    |
    | Si existe una sesión válida se guarda la relación.
    |
    */

                    if ($cashSession) {

                        $salePaymentData['cash_register_id'] =
                            $cashSession->cash_register_id;

                        $salePaymentData['cash_register_session_id'] =
                            $cashSession->id;
                    }

                    /*
    |--------------------------------------------------------------------------
    | GUARDAR PAGO
    |--------------------------------------------------------------------------
    */

                    $salePayment = SalePayment::create(
                        $salePaymentData
                    );

                    $createdPayments[] = $salePayment;

                    /*
    |--------------------------------------------------------------------------
    | MOVIMIENTO DE CAJA
    |--------------------------------------------------------------------------
    |
    | SOLAMENTE CASH mueve físicamente el efectivo.
    |
    */


                    if ($paymentMethod === 'CASH') {

                        if (!$cashSession) {

                            throw new \Exception(
                                'No existe una sesión de caja abierta.'
                            );
                        }

                        CashMovement::create([

                            'id_company' =>
                            $idCompany,

                            'branch_id' =>
                            $branchId,

                            'cash_register_id' =>
                            $cashSession->cash_register_id,

                            'cash_register_session_id' =>
                            $cashSession->id,

                            'user_id' =>
                            $user->id,

                            'sale_id' =>
                            $sale->id,

                            'sale_payment_id' =>
                            $salePayment->id,

                            'type' =>
                            'INGRESO',

                            'amount' =>
                            $amount,

                            'description' =>
                            'Venta ' . $sale->code_sale,
                        ]);
                    }
                }
                /*
                |--------------------------------------------------------------------------
                | 21. CUENTAS CONTABLES DINÁMICAS
                |--------------------------------------------------------------------------
                */

                $salesAccount =
                    $this->accountingService->getMappedAccount(
                        $idCompany,
                        'VENTA'
                    );

                $receivableAccount = null;

                if ($usedBalance > 0) {
                    $receivableAccount =
                        $this->accountingService->getMappedAccount(
                            $idCompany,
                            'CUENTA_POR_COBRAR'
                        );
                }

                /*
                |--------------------------------------------------------------------------
                | 22. CUENTAS DE LOS MÉTODOS DE PAGO
                |--------------------------------------------------------------------------
                */

                $accountingAmounts = [];

                foreach ($createdPayments as $payment) {

                    $paymentMethod = strtoupper(
                        $payment->payment_method
                    );

                    $amount = round(
                        (float) $payment->amount,
                        2
                    );

                    $paymentAccount =
                        $this->accountingService->getPaymentAccount(
                            $idCompany,
                            $paymentMethod,
                            $cashSession
                        );

                    $accountId = $paymentAccount->id;

                    if (!isset($accountingAmounts[$accountId])) {
                        $accountingAmounts[$accountId] = [
                            'account' => $paymentAccount,
                            'amount' => 0,
                        ];
                    }

                    $accountingAmounts[$accountId]['amount'] =
                        round(
                            $accountingAmounts[$accountId]['amount'] + $amount,
                            2
                        );
                }

                /*
                |--------------------------------------------------------------------------
                | 23. VALIDAR CUADRE CONTABLE
                |--------------------------------------------------------------------------
                */

                $totalDebit = 0;

                foreach ($accountingAmounts as $item) {
                    $totalDebit += (float) $item['amount'];
                }

                $totalDebit += (float) $usedBalance;
                $totalDebit = round($totalDebit, 2);

                if (abs($totalDebit - $total) > 0.01) {
                    throw new \Exception(
                        'El asiento contable no cuadra con el total de la venta. ' .
                            'Debe: ' . $totalDebit .
                            ' - Total venta: ' . $total
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | 24. PERÍODO CONTABLE
                |--------------------------------------------------------------------------
                */

                $accountingPeriod =
                    AccountingPeriod::query()
                    ->where('id_company', $idCompany)
                    ->where('status', 'OPEN')
                    ->first();

                if (!$accountingPeriod) {
                    throw new \Exception(
                        'No existe un período contable abierto para registrar la venta.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | 25. CONSTRUIR DETALLES CONTABLES
                |--------------------------------------------------------------------------
                */

                $accountingDetails = [];

                foreach ($accountingAmounts as $item) {
                    $accountingDetails[] = [
                        'accounting_account_id' => $item['account']->id,
                        'description' =>
                        'Pago de venta ' . $sale->code_sale,
                        'debit' => round($item['amount'], 2),
                        'credit' => 0,
                    ];
                }

                if ($usedBalance > 0) {
                    $accountingDetails[] = [
                        'accounting_account_id' =>
                        $receivableAccount->id,
                        'description' =>
                        'Saldo utilizado del cliente en venta ' .
                            $sale->code_sale,
                        'debit' => round($usedBalance, 2),
                        'credit' => 0,
                    ];
                }

                /*
                |--------------------------------------------------------------------------
                | 26. CUENTA DE VENTAS
                |--------------------------------------------------------------------------
                */

                $accountingDetails[] = [
                    'accounting_account_id' => $salesAccount->id,
                    'description' => 'Venta ' . $sale->code_sale,
                    'debit' => 0,
                    'credit' => round($total, 2),
                ];

                /*
                |--------------------------------------------------------------------------
                | 27. CREAR ASIENTO MEDIANTE ACCOUNTINGSERVICE
                |--------------------------------------------------------------------------
                |
                | El servicio genera ASI-XXXXXX, crea los detalles y
                | verifica automáticamente que Debe = Haber.
                |
                */

                $accountingEntry =
                    $this->accountingService->createEntry(
                        [
                            'id_company' => $idCompany,
                            'branch_id' => $branchId,
                            'accounting_period_id' => $accountingPeriod->id,
                            'date' => now(),
                            'type' => 'VENTA',
                            'description' =>
                            'Venta ' . $sale->code_sale,
                            'reference_type' => Sale::class,
                            'reference_id' => $sale->id,
                            'user_id' => $user->id,
                            'status' => 'POSTED',
                        ],
                        $accountingDetails
                    );

                /*
                |--------------------------------------------------------------------------
                | 35. ACTUALIZAR SALDO CLIENTE
                |--------------------------------------------------------------------------
                */

                $newBalance =
                    round(
                        $clientBalance -
                            $usedBalance,
                        2
                    );

                if ($usedBalance > 0) {

                    $client->balance =
                        $newBalance;

                    $client->save();
                }

                /*
                |--------------------------------------------------------------------------
                | 36. PROCESAR STOCK, DETALLE Y KARDEX
                |--------------------------------------------------------------------------
                */

                foreach ($validatedItems as $item) {

                    $product =
                        $item['product'];

                    $stockProduct =
                        $item['stock'];

                    $quantity =
                        $item['quantity'];

                    $unitPrice =
                        $item['unit_price'];

                    $detailSubtotal =
                        $item['subtotal'];

                    /*
                    |--------------------------------------------------------------------------
                    | STOCK ANTES
                    |--------------------------------------------------------------------------
                    */

                    $stockBefore =
                        round(
                            (float) $stockProduct->current_stock,
                            2
                        );

                    /*
                    |--------------------------------------------------------------------------
                    | RESTAR STOCK
                    |--------------------------------------------------------------------------
                    */

                    $stockAfter =
                        round(
                            $stockBefore -
                                $quantity,
                            2
                        );

                    $stockProduct->current_stock =
                        $stockAfter;

                    $stockProduct->save();

                    /*
                    |--------------------------------------------------------------------------
                    | 37. CREAR CÓDIGO DETALLE
                    |--------------------------------------------------------------------------
                    */

                    $lastDetail =
                        SaleDetail::query()
                        ->orderByDesc('id')
                        ->lockForUpdate()
                        ->first();

                    if ($lastDetail) {

                        preg_match(
                            '/(\d+)$/',
                            $lastDetail->code_sale_detail,
                            $matches
                        );

                        $detailNumber =
                            isset($matches[1])
                            ? ((int) $matches[1]) + 1
                            : 1;
                    } else {

                        $detailNumber = 1;
                    }

                    $codeSaleDetail =
                        'VD-' .
                        str_pad(
                            $detailNumber,
                            6,
                            '0',
                            STR_PAD_LEFT
                        );

                    /*
                    |--------------------------------------------------------------------------
                    | 38. CREAR SALE DETAIL
                    |--------------------------------------------------------------------------
                    */

                    $saleDetail =
                        SaleDetail::create([

                            'code_sale_detail' =>
                            $codeSaleDetail,

                            'product_id' =>
                            $product->id,

                            'sale_id' =>
                            $sale->id,

                            'quantity' =>
                            $quantity,

                            'unit_price' =>
                            $unitPrice,

                            'subtotal' =>
                            $detailSubtotal,

                            'discount' =>
                            0,

                            'iva' =>
                            0,

                            'total' =>
                            $detailSubtotal,

                            'returned' =>
                            false,

                            'returned_quantity' =>
                            0,
                        ]);

                    /*
                    |--------------------------------------------------------------------------
                    | 39. REGISTRAR MOVIMIENTO KARDEX
                    |--------------------------------------------------------------------------
                    |
                    | AQUÍ se registra la salida por venta.
                    |
                    */

                    StockMovement::create([

                        'id_company' =>
                        $idCompany,

                        'branch_id' =>
                        $branchId,

                        'product_id' =>
                        $product->id,

                        'user_id' =>
                        $user->id,

                        'movement_type' =>
                        'SALE',

                        'quantity' =>
                        $quantity,

                        'reference_type' =>
                        'sale',

                        'reference_id' =>
                        $sale->id,

                        'note' =>
                        'Venta ' .
                            $sale->code_sale .
                            ' - Detalle ' .
                            $saleDetail->code_sale_detail,
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | 40. RESULTADO
                |--------------------------------------------------------------------------
                */

                return [

                    'sale' =>
                    $sale,

                    'payments' =>
                    $createdPayments,

                    'accounting_entry' =>
                    $accountingEntry,

                    'client_balance_before' =>
                    $clientBalance,

                    'client_balance_used' =>
                    $usedBalance,

                    'client_balance_after' =>
                    $newBalance,

                    'total_final' =>
                    $totalFinal,
                ];
            });

            /*
            |--------------------------------------------------------------------------
            | 41. CARGAR RELACIONES
            |--------------------------------------------------------------------------
            */

            $sale =
                $result['sale'];

            $sale->load([

                'company',

                'user',

                'client',

                'branch',

                'details.product',

                'payments',

            ]);

            $pdf = $this->salePdfService->generar(
                $sale,
                $result['client_balance_before'],
                $result['client_balance_used'],
                $result['client_balance_after'],
                $result['total_final']
            );
            /*
            |--------------------------------------------------------------------------
            | 42. RESPUESTA
            |--------------------------------------------------------------------------
            */

            return response()->json([

                'success' =>
                true,

                'message' =>
                'Venta registrada correctamente.',

                'data' => [

                    'id' =>
                    $sale->id,

                    'code_sale' =>
                    $sale->code_sale,

                    'receipt_number' =>
                    $sale->receipt_number,

                    'id_company' =>
                    $sale->id_company,

                    'branch_id' =>
                    $sale->branch_id,

                    'client_id' =>
                    $sale->client_id,

                    'user_id' =>
                    $sale->user_id,

                    'subtotal' =>
                    $subtotal,

                    'discount' =>
                    $discount,

                    'total' =>
                    $total,

                    'usedBalance' =>
                    $result['client_balance_used'],

                    'totalFinal' =>
                    $result['total_final'],

                    'clientBalanceBefore' =>
                    $result['client_balance_before'],

                    'clientBalanceAfter' =>
                    $result['client_balance_after'],

                    /*
                    |--------------------------------------------------------------------------
                    | ASIENTO
                    |--------------------------------------------------------------------------
                    */

                    'accounting_entry' => [

                        'id' =>
                        $result['accounting_entry']->id,

                        'code_entry' =>
                        $result['accounting_entry']->code_entry,

                        'type' =>
                        $result['accounting_entry']->type,

                        'description' =>
                        $result['accounting_entry']->description,

                        'status' =>
                        $result['accounting_entry']->status,
                    ],

                    /*
                    |--------------------------------------------------------------------------
                    | PAGOS
                    |--------------------------------------------------------------------------
                    */

                    'payments' =>
                    $sale->payments->map(
                        function ($payment) {

                            return [

                                'id' =>
                                $payment->id,

                                'payment_method' =>
                                $payment->payment_method,

                                'amount' =>
                                $payment->amount,

                                'reference' =>
                                $payment->reference,

                                'observation' =>
                                $payment->observation,
                            ];
                        }
                    ),

                    /*
                    |--------------------------------------------------------------------------
                    | STOCK
                    |--------------------------------------------------------------------------
                    */

                    'stock' =>
                    $sale->details->map(
                        function ($detail) {

                            return [

                                'product_id' =>
                                $detail->product_id,

                                'quantity' =>
                                $detail->quantity,

                                'unit_price' =>
                                $detail->unit_price,

                                'subtotal' =>
                                $detail->subtotal,

                                'total' =>
                                $detail->total,
                            ];
                        }
                    ),
                ],
                'pdf' => [
                    'url' => $pdf['url'],
                    'filename' => $pdf['filename'],
                    'path' => $pdf['path'],
                ],

            ], 201);
        } catch (
            \Illuminate\Validation\ValidationException $e
        ) {

            return response()->json([

                'success' =>
                false,

                'message' =>
                'Los datos enviados no son válidos.',

                'errors' =>
                $e->errors(),

            ], 422);
        } catch (\Throwable $e) {

            return response()->json([

                'success' =>
                false,

                'message' =>
                'Error al registrar la venta.',

                'error' =>
                $e->getMessage(),

                'file' =>
                $e->getFile(),

                'line' =>
                $e->getLine(),

            ], 500);
        }
    }
















    

    /**
     * ============================================================
     * LISTAR VENTAS DEL USUARIO
     * ============================================================
     */
    public function index(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | 1. USUARIO
        |--------------------------------------------------------------------------
        */

        $user = Auth::user();

        if (!$user) {

            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | 2. EMPRESA
        |--------------------------------------------------------------------------
        */

        $idCompany = $user->id_company;

        if (!$idCompany) {

            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una empresa asignada.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | 3. PAGINACIÓN
        |--------------------------------------------------------------------------
        */

        $perPage =
            (int) $request->get(
                'per_page',
                10
            );

        if ($perPage < 1) {

            $perPage = 10;
        }

        if ($perPage > 100) {

            $perPage = 100;
        }

        /*
        |--------------------------------------------------------------------------
        | 4. CONSULTA
        |--------------------------------------------------------------------------
        */

        $query = Sale::query()

            ->where(
                'id_company',
                $idCompany
            )

            ->where(
                'user_id',
                $user->id
            )

            ->with([

                'company',

                'user',

                'client',

                'branch',

                'details.product',

                'payments',

            ]);

        /*
        |--------------------------------------------------------------------------
        | 5. FILTRO CLIENTE
        |--------------------------------------------------------------------------
        */

        if ($request->filled('cliente')) {

            $cliente =
                trim(
                    $request->cliente
                );

            $query->whereHas(
                'client',
                function ($q) use ($cliente) {

                    $q->where(
                        function ($subQuery) use ($cliente) {

                            $subQuery

                                ->where(
                                    'name',
                                    'LIKE',
                                    "%{$cliente}%"
                                )

                                ->orWhere(
                                    'ciNit',
                                    'LIKE',
                                    "%{$cliente}%"
                                );
                        }
                    );
                }
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 6. FILTRO PRODUCTO
        |--------------------------------------------------------------------------
        */

        if ($request->filled('producto')) {

            $producto =
                trim(
                    $request->producto
                );

            $query->whereHas(
                'details.product',
                function ($q) use ($producto) {

                    $q->where(
                        function ($subQuery) use ($producto) {

                            $subQuery

                                ->where(
                                    'name',
                                    'LIKE',
                                    "%{$producto}%"
                                )

                                ->orWhere(
                                    'code',
                                    'LIKE',
                                    "%{$producto}%"
                                );
                        }
                    );
                }
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 7. FECHA INICIO
        |--------------------------------------------------------------------------
        */

        if ($request->filled('fechaInicio')) {

            $query->whereDate(
                'date',
                '>=',
                $request->fechaInicio
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 8. FECHA FIN
        |--------------------------------------------------------------------------
        */

        if ($request->filled('fechaFin')) {

            $query->whereDate(
                'date',
                '<=',
                $request->fechaFin
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 9. ORDEN
        |--------------------------------------------------------------------------
        */

        $sortField =
            $request->get(
                'sortField',
                'date'
            );

        $sortOrder =
            strtolower(
                $request->get(
                    'sortOrder',
                    'desc'
                )
            );

        $allowedSortFields = [

            'id',

            'date',

            'receipt_number',

            'discount',

            'total',

        ];

        if (
            !in_array(
                $sortField,
                $allowedSortFields
            )
        ) {

            $sortField = 'date';
        }

        if (
            !in_array(
                $sortOrder,
                ['asc', 'desc']
            )
        ) {

            $sortOrder = 'desc';
        }

        $query->orderBy(
            $sortField,
            $sortOrder
        );

        /*
        |--------------------------------------------------------------------------
        | 10. PAGINAR
        |--------------------------------------------------------------------------
        */

        $sales =
            $query->paginate(
                $perPage
            );

        /*
        |--------------------------------------------------------------------------
        | 11. RESPUESTA
        |--------------------------------------------------------------------------
        */

        return response()->json([

            'success' =>
            true,

            'message' =>
            'Ventas obtenidas correctamente.',

            'data' =>
            $sales,

        ]);
    }
}
