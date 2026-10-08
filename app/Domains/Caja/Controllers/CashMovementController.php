<?php

namespace App\Domains\Caja\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use App\Domains\Caja\Models\CashRegister;
use App\Domains\Caja\Models\CashRegisterSession;
use App\Domains\Caja\Models\CashMovement;
use App\Domains\Ventas\Models\SalePayment;


class CashMovementController extends Controller
{
    /**
     * ============================================================
     * OBTENER PAGOS DE VENTA PENDIENTES DE COBRO
     * ============================================================
     *
     * Trabajamos directamente con:
     *
     *      sale_payments.is_collected
     *
     * NO usamos:
     *
     *      cash_movements.is_collected
     *
     */
    public function pendingCollections(Request $request)
    {
        $user = Auth::user();

        /*
        |--------------------------------------------------------------------------
        | USUARIO
        |--------------------------------------------------------------------------
        */

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

        if (!$user->id_company) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una empresa asignada.'
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | PAGINACIÓN
        |--------------------------------------------------------------------------
        */

        $perPage = (int) $request->input('per_page', 10);

        if ($perPage < 1) {
            $perPage = 10;
        }

        if ($perPage > 100) {
            $perPage = 100;
        }

        /*
        |--------------------------------------------------------------------------
        | QUERY
        |--------------------------------------------------------------------------
        */

       $query = SalePayment::query()
    ->with([
        'sale',
        'cashRegister',
        'cashRegisterSession',
    ])
    ->where('id_company', $user->id_company)
    ->where('is_collected', false)
    ->whereHas('sale', function ($q) use ($user) {
        $q->where('user_id', $user->id);
    });
        /*
        |--------------------------------------------------------------------------
        | BUSCAR
        |--------------------------------------------------------------------------
        |
        | Busca por:
        | - ID del pago
        | - ID de venta
        | - método de pago
        | - referencia
        | - observación
        |
        */

        if ($request->filled('search')) {

            $search = trim($request->input('search'));

            $query->where(function ($q) use ($search) {

                $q->where('id', 'like', "%{$search}%")
                    ->orWhere('sale_id', 'like', "%{$search}%")
                    ->orWhere('payment_method', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhere('observation', 'like', "%{$search}%");
            });
        }

        /*
        |--------------------------------------------------------------------------
        | SUCURSAL
        |--------------------------------------------------------------------------
        */

        if ($request->filled('branch_id')) {

            $branchId = $request->input('branch_id');

            $query->whereHas('cashRegister', function ($q) use ($branchId) {

                $q->where('branch_id', $branchId);

            });
        }

        /*
        |--------------------------------------------------------------------------
        | CAJA
        |--------------------------------------------------------------------------
        */

        if ($request->filled('cash_register_id')) {

            $query->where(
                'cash_register_id',
                $request->input('cash_register_id')
            );
        }

        /*
        |--------------------------------------------------------------------------
        | MÉTODO DE PAGO
        |--------------------------------------------------------------------------
        */

        if ($request->filled('payment_method')) {

            $query->where(
                'payment_method',
                $request->input('payment_method')
            );
        }

        /*
        |--------------------------------------------------------------------------
        | FECHA DESDE
        |--------------------------------------------------------------------------
        */

        if ($request->filled('date_from')) {

            $query->whereDate(
                'created_at',
                '>=',
                $request->input('date_from')
            );
        }

        /*
        |--------------------------------------------------------------------------
        | FECHA HASTA
        |--------------------------------------------------------------------------
        */

        if ($request->filled('date_to')) {

            $query->whereDate(
                'created_at',
                '<=',
                $request->input('date_to')
            );
        }

        /*
        |--------------------------------------------------------------------------
        | ORDEN
        |--------------------------------------------------------------------------
        */

        $query->orderBy(
            'created_at',
            'desc'
        );

        /*
        |--------------------------------------------------------------------------
        | PAGINAR
        |--------------------------------------------------------------------------
        */

        $payments = $query->paginate($perPage);

        /*
        |--------------------------------------------------------------------------
        | RESPUESTA
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,
            'data' => $payments
        ]);
    }



    /**
     * ============================================================
     * COBRAR PAGOS SELECCIONADOS
     * ============================================================
     *
     * Recibe:
     *
     * {
     *     "payment_ids": [1, 2, 3]
     * }
     *
     * y cambia:
     *
     *      is_collected = false
     *
     * a:
     *
     *      is_collected = true
     *
     */
    public function collect(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        if (!$user->id_company) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una empresa asignada.',
            ], 422);
        }


        // =========================================================
        // VALIDACIÓN
        // =========================================================

        $validated = $request->validate([
    'payment_ids' => ['required', 'array', 'min:1'],
    'payment_ids.*' => ['required', 'integer', 'distinct'],
]);


        try {

            $result = DB::transaction(function () use (
                $validated,
                $user
            ) {

                // =================================================
                // OBTENER PAGOS
                // =================================================

                $payments = SalePayment::query()
    ->whereIn('id', $validated['payment_ids'])
    ->where('id_company', $user->id_company)
    ->where('is_collected', false)
    ->lockForUpdate()
    ->get();


                // =================================================
                // VERIFICAR TODOS
                // =================================================

                if (
                    $payments->count()
                    !== count($validated['payment_ids'])
                ) {

                    throw new \Exception(
                        'Uno o más pagos ya fueron cobrados, no existen o no pertenecen a la empresa.'
                    );
                }


                // =================================================
                // TOTAL
                // =================================================

                $total = $payments->sum(
                    'amount'
                );


                // =================================================
                // RESUMEN POR MÉTODO
                // =================================================

                $cash = $payments
                    ->filter(function ($payment) {

                        return strtoupper(
                            (string) $payment->payment_method
                        ) === 'CASH';

                    })
                    ->sum('amount');


                $card = $payments
                    ->filter(function ($payment) {

                        return strtoupper(
                            (string) $payment->payment_method
                        ) === 'CARD';

                    })
                    ->sum('amount');


                $qr = $payments
                    ->filter(function ($payment) {

                        return strtoupper(
                            (string) $payment->payment_method
                        ) === 'QR';

                    })
                    ->sum('amount');


                $transfer = $payments
                    ->filter(function ($payment) {

                        return strtoupper(
                            (string) $payment->payment_method
                        ) === 'TRANSFER';

                    })
                    ->sum('amount');


                $other = $payments
                    ->filter(function ($payment) {

                        $method = strtoupper(
                            (string) $payment->payment_method
                        );

                        return !in_array($method, [
                            'CASH',
                            'CARD',
                            'QR',
                            'TRANSFER',
                        ]);

                    })
                    ->sum('amount');


                // =================================================
                // MARCAR COMO COBRADOS
                // =================================================

                $updated = SalePayment::query()
                    ->whereIn(
                        'id',
                        $validated['payment_ids']
                    )
                    ->where(
                        'id_company',
                        $user->id_company
                    )
                    ->where(
                        'is_collected',
                        false
                    )
                    ->update([
                        'is_collected' => true,
                        'updated_at' => now(),
                    ]);


                return [

                    'count' => $updated,

                    'total' => (float) $total,

                    'cash' => (float) $cash,

                    'card' => (float) $card,

                    'qr' => (float) $qr,

                    'transfer' => (float) $transfer,

                    'other' => (float) $other,

                    'payment_ids' =>
                        $validated['payment_ids'],
                ];
            });


            // =====================================================
            // RESPUESTA
            // =====================================================

            return response()->json([

                'success' => true,

                'message' =>
                    'Los pagos fueron cobrados correctamente.',

                'data' => [

                    'count' =>
                        $result['count'],

                    'total' =>
                        number_format(
                            $result['total'],
                            2,
                            '.',
                            ''
                        ),

                    'cash' =>
                        number_format(
                            $result['cash'],
                            2,
                            '.',
                            ''
                        ),

                    'card' =>
                        number_format(
                            $result['card'],
                            2,
                            '.',
                            ''
                        ),

                    'qr' =>
                        number_format(
                            $result['qr'],
                            2,
                            '.',
                            ''
                        ),

                    'transfer' =>
                        number_format(
                            $result['transfer'],
                            2,
                            '.',
                            ''
                        ),

                    'other' =>
                        number_format(
                            $result['other'],
                            2,
                            '.',
                            ''
                        ),

                    'payment_ids' =>
                        $result['payment_ids'],
                ],
            ]);


        } catch (\Throwable $e) {

            return response()->json([

                'success' => false,

                'message' =>
                    $e->getMessage(),

            ], 422);
        }
    }
    /**
     * ============================================================
     * REGISTRAR MOVIMIENTO MANUAL
     * ============================================================
     */
    public function store(Request $request, $sessionId)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'type' => [
                'required',
                'in:INCOME,EXPENSE,WITHDRAWAL,ADJUSTMENT'
            ],

            'amount' => [
                'required',
                'numeric',
                'gt:0'
            ],

            'description' => [
                'nullable',
                'string',
                'max:1000'
            ],

            'payment_method' => [
                'nullable',
                'in:CASH,QR,CARD,TRANSFER,CHECK,BALANCE,OTHER'
            ]
        ]);

        /*
        |--------------------------------------------------------------------------
        | SESIÓN
        |--------------------------------------------------------------------------
        */

        $session = CashRegisterSession::where(
            'id_company',
            $user->id_company
        )
            ->where('id', $sessionId)
            ->where('user_id', $user->id)
            ->where('status', 'OPEN')
            ->first();

        if (!$session) {

            return response()->json([
                'success' => false,
                'message' => 'La sesión no existe o está cerrada.'
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | CAJA
        |--------------------------------------------------------------------------
        */

        $cashRegister = CashRegister::where(
            'id_company',
            $user->id_company
        )
            ->where('id', $session->cash_register_id)
            ->first();

        if (!$cashRegister) {

            return response()->json([
                'success' => false,
                'message' => 'No se encontró la caja.'
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | DETERMINAR IN / OUT
        |--------------------------------------------------------------------------
        */

        $movement = match ($validated['type']) {

            'INCOME' => 'IN',

            'EXPENSE' => 'OUT',

            'WITHDRAWAL' => 'OUT',

            'ADJUSTMENT' => 'IN',

            default => 'IN'
        };

        /*
        |--------------------------------------------------------------------------
        | MÉTODO
        |--------------------------------------------------------------------------
        */

        $paymentMethod =
            $validated['payment_method'] ?? 'CASH';

        /*
        |--------------------------------------------------------------------------
        | REGISTRAR
        |--------------------------------------------------------------------------
        */

        DB::beginTransaction();

        try {

            $cashMovement = CashMovement::create([

                'id_company' =>
                $user->id_company,

                'branch_id' =>
                $cashRegister->branch_id,

                'cash_register_id' =>
                $cashRegister->id,

                'cash_register_session_id' =>
                $session->id,

                'user_id' =>
                $user->id,

                /*  'sale_id' =>
                    null, */

                'type' =>
                $validated['type'],

                'movement' =>
                $movement,

                'amount' =>
                $validated['amount'],

                'payment_method' =>
                $paymentMethod,

                'description' =>
                $validated['description'] ?? null,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,

                'message' =>
                'Movimiento registrado correctamente.',

                'data' =>
                $cashMovement
            ]);
        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,

                'message' =>
                'No se pudo registrar el movimiento.',

                'error' =>
                $e->getMessage()
            ], 500);
        }
    }
}

/*
<?php

namespace App\Domains\Caja\Controllers;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Domains\Caja\Services\CashMovementService;
use App\Domains\Caja\Models\CashMovement;

class CashMovementController extends Controller
{
    public function __construct(
        protected CashMovementService $service
    ) {}

    public function index(Request $request)
    {
        $user = Auth::user();

        $companyId = $user->id_company;

        $query = CashMovement::query()
            ->where('id_company', $companyId)
            ->with([
                'category',
                'cashRegister',
                'session',
                'user',
            ]);

        if ($request->filled('cash_register_id')) {
            $query->where(
                'cash_register_id',
                $request->cash_register_id
            );
        }

        if ($request->filled('cash_register_session_id')) {
            $query->where(
                'cash_register_session_id',
                $request->cash_register_session_id
            );
        }

        if ($request->filled('type')) {
            $query->where(
                'type',
                $request->type
            );
        }

        if ($request->filled('date_from')) {
            $query->whereDate(
                'created_at',
                '>=',
                $request->date_from
            );
        }

        if ($request->filled('date_to')) {
            $query->whereDate(
                'created_at',
                '<=',
                $request->date_to
            );
        }

        return response()->json(
            $query
                ->latest()
                ->paginate(
                    min(
                        $request->integer('per_page', 20),
                        100
                    )
                )
        );
    }

    public function store(
        Request $request
    ) {
        $user = Auth::user();

        $validated = $request->validate([

            'cash_register_session_id' =>
                'required|integer',

            'cash_category_id' =>
                'required|integer',

            'type' =>
                'required|in:INGRESO,EGRESO',

            'amount' =>
                'required|numeric|min:0.01',

            'description' =>
                'nullable|string|max:500',
        ]);

        $validated['id_company'] =
            $user->id_company;

        $validated['user_id'] =
            $user->id;

        $movement =
            $this->service->create(
                $validated
            );

        return response()->json([
            'message' =>
                'Movimiento registrado correctamente.',
            'data' =>
                $movement,
        ], 201);
    }

    public function show(
        CashMovement $cashMovement
    ) {
        $user = Auth::user();

        abort_unless(
            $cashMovement->id_company ===
            $user->id_company,
            403
        );

        return response()->json([
            'data' =>
                $cashMovement->load([
                    'category',
                    'cashRegister',
                    'session',
                    'user',
                ]),
        ]);
    }
}
*/