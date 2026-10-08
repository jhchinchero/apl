<?php

namespace App\Domains\Caja\Controllers;

use App\Http\Controllers\Controller;

use App\Domains\Caja\Models\CashRegister;
use App\Domains\Caja\Models\CashRegisterSession;
use App\Domains\Caja\Models\CashMovement;

use App\Domains\Ventas\Models\SalePayment;

use App\Domains\Seguridad\Models\User;
use App\Domains\Contabilidad\Models\AccountingAccount;

use App\Domains\Contabilidad\Models\AccountingPaymentMapping;
use App\Domains\Contabilidad\Models\AccountingAccountMapping;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CashRegisterSessionController extends Controller
{

        /*
    |--------------------------------------------------------------------------
    | MI CAJA
    |--------------------------------------------------------------------------
    */

    public function myCashRegister()
{
    $user = Auth::user();

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'Usuario no autenticado.'
        ], 401);
    }

    $cashRegisters = CashRegister::where('id_company', $user->id_company)
        ->where('status', true)
        ->whereHas('users', function ($query) use ($user) {
            $query->where('users.id', $user->id)
                  ->where('cash_register_user.status', true);
        })
        ->with([
            'branch',
            'pointOfSale',
            'openSession',
        ])
        ->orderBy('name')
        ->get();

    if ($cashRegisters->isEmpty()) {
        return response()->json([
            'success' => false,
            'message' => 'No tiene cajas asignadas.',
            'data' => []
        ], 404);
    }

    return response()->json([
        'success' => true,
        'data' => $cashRegisters
    ]);
}


    public function getPlanCuentasVentas(Request $request)
    {
        $user = Auth::user();

        $idCompany = $user->id_company;

        $accounts = AccountingAccount::with('accountType')
            ->where('id_company', $idCompany)
            ->where('status', true)
            ->where('allows_entries', true)
            ->whereHas('accountType', function ($query) {
                $query->where('code', 'ACT'); //ACTIVO
            })
            ->orderBy('code')
            ->get([
                'id',
                'id_company',
                'account_type_id',
                'code',
                'name',
                'level',
                'parent_id',
                'allows_entries',
                'status',
            ]);

        return response()->json([
            'success' => true,
            'data' => $accounts,
        ]);
    }
    /*
    |--------------------------------------------------------------------------
    | LISTAR SESIONES
    |--------------------------------------------------------------------------
    */

    public function index($cashRegister)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.'
            ], 401);
        }

        $companyId = $user->id_company;

        $cashRegisterModel = CashRegister::where('id', $cashRegister)
            ->where('id_company', $companyId)
            ->first();

        if (!$cashRegisterModel) {
            return response()->json([
                'success' => false,
                'message' => 'La caja no existe o no pertenece a la empresa.'
            ], 404);
        }

        $sessions = CashRegisterSession::where(
            'cash_register_id',
            $cashRegisterModel->id
        )
            ->where('id_company', $companyId)
            ->with([
                'user:id,name,email',
                'cashRegister.branch',
                'cashRegister.pointOfSale'
            ])
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $sessions
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | SESIÓN ACTUAL
    |--------------------------------------------------------------------------
    */

    public function currentSession($cashRegister)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.'
            ], 401);
        }

        $companyId = $user->id_company;

        /*
        |--------------------------------------------------------------------------
        | CAJA
        |--------------------------------------------------------------------------
        */

        $cashRegisterModel = CashRegister::where('id', $cashRegister)
            ->where('id_company', $companyId)
            ->first();

        if (!$cashRegisterModel) {
            return response()->json([
                'success' => false,
                'message' => 'La caja no existe o no pertenece a la empresa.'
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | SESIÓN ABIERTA
        |--------------------------------------------------------------------------
        */

        $session = CashRegisterSession::where(
            'cash_register_id',
            $cashRegisterModel->id
        )
            ->where('id_company', $companyId)
            ->where('user_id', $user->id)
            ->where('status', 'OPEN')
            ->latest('id')
            ->first();

        /*
        |--------------------------------------------------------------------------
        | NO EXISTE SESIÓN
        |--------------------------------------------------------------------------
        */

        if (!$session) {
            return response()->json([
                'success' => true,

                'data' => [
                    'session' => null,
                    'summary' => null
                ],

                'message' => 'La caja no tiene una sesión abierta.'
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | RELACIONES
        |--------------------------------------------------------------------------
        */

        $session->load([
            'user:id,name,email',
            'cashRegister.branch',
            'cashRegister.pointOfSale'
        ]);

        /*
        |--------------------------------------------------------------------------
        | CALCULAR RESUMEN
        |--------------------------------------------------------------------------
        */

        $summary = $this->calculateExpectedAmount($session);

        /*
        |--------------------------------------------------------------------------
        | RECARGAR SESIÓN
        |--------------------------------------------------------------------------
        */

        $session->refresh();

        $session->load([
            'user:id,name,email',
            'cashRegister.branch',
            'cashRegister.pointOfSale'
        ]);

        /*
        |--------------------------------------------------------------------------
        | RESPUESTA
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,

            'data' => [
                'session' => $session,
                'summary' => $summary
            ]
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | RESUMEN DE CAJA
    |--------------------------------------------------------------------------
    */

    public function summary($cashRegister)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.'
            ], 401);
        }

        $companyId = $user->id_company;

        /*
        |--------------------------------------------------------------------------
        | CAJA
        |--------------------------------------------------------------------------
        */

        $cashRegisterModel = CashRegister::where('id', $cashRegister)
            ->where('id_company', $companyId)
            ->first();

        if (!$cashRegisterModel) {
            return response()->json([
                'success' => false,
                'message' => 'La caja no existe o no pertenece a la empresa.'
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | SESIÓN ABIERTA
        |--------------------------------------------------------------------------
        */

        $session = CashRegisterSession::where(
            'cash_register_id',
            $cashRegisterModel->id
        )
            ->where('id_company', $companyId)
            ->where('user_id', $user->id)
            ->where('status', 'OPEN')
            ->latest('id')
            ->first();

        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'No existe una sesión abierta.'
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | CALCULAR
        |--------------------------------------------------------------------------
        */

        $summary = $this->calculateExpectedAmount($session);

        $session->refresh();

        $session->load([
            'user:id,name,email',
            'cashRegister.branch',
            'cashRegister.pointOfSale'
        ]);

        return response()->json([
            'success' => true,

            'data' => [
                'session' => $session,
                'summary' => $summary
            ]
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | ABRIR CAJA
    |--------------------------------------------------------------------------
    */

    public function openSession(Request $request, $cashRegister)
    {
        $authUser = Auth::user();

        if (!$authUser) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.'
            ], 401);
        }

        $companyId = $authUser->id_company;
        $userId = $authUser->id;

        $validated = $request->validate([
            'opening_amount' => [
                'required',
                'numeric',
                'min:0'
            ],

            'opening_note' => [
                'nullable',
                'string',
                'max:1000'
            ],
        ]);

        DB::beginTransaction();

        try {

            /*
            |--------------------------------------------------------------------------
            | CAJA
            |--------------------------------------------------------------------------
            */

            $cashRegisterModel = CashRegister::where(
                'id',
                $cashRegister
            )
                ->where(
                    'id_company',
                    $companyId
                )
                ->first();

            if (!$cashRegisterModel) {
                throw new \Exception(
                    'La caja no existe o no pertenece a la empresa.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | CAJA ACTIVA
            |--------------------------------------------------------------------------
            */

            if (!$cashRegisterModel->status) {
                throw new \Exception(
                    'No se puede abrir una caja inactiva.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | USUARIO
            |--------------------------------------------------------------------------
            */

            $user = User::where('id', $userId)
                ->where('id_company', $companyId)
                ->where('status', true)
                ->first();

            if (!$user) {
                throw new \Exception(
                    'El usuario autenticado no existe, está inactivo o no pertenece a la empresa.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | ASIGNACIÓN DE CAJA
            |--------------------------------------------------------------------------
            */

            $assigned = DB::table('cash_register_user')
                ->where('id_company', $companyId)
                ->where('cash_register_id', $cashRegisterModel->id)
                ->where('user_id', $user->id)
                ->where('status', true)
                ->exists();

            if (!$assigned) {
                throw new \Exception(
                    'El usuario no está asignado a esta caja.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | CAJA YA ABIERTA
            |--------------------------------------------------------------------------
            */

            $existingSession = CashRegisterSession::where(
                'cash_register_id',
                $cashRegisterModel->id
            )
                ->where('id_company', $companyId)
                ->where('status', 'OPEN')
                ->lockForUpdate()
                ->first();

            if ($existingSession) {

                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Esta caja ya tiene una sesión abierta.',
                    'data' => [
                        'session_id' => $existingSession->id,
                        'user_id' => $existingSession->user_id,
                        'opened_at' => $existingSession->opened_at
                    ]
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | USUARIO CON OTRA CAJA ABIERTA
            |--------------------------------------------------------------------------
            */

            $userOpenSession = CashRegisterSession::where(
                'user_id',
                $user->id
            )
                ->where('id_company', $companyId)
                ->where('status', 'OPEN')
                ->lockForUpdate()
                ->first();

            if ($userOpenSession) {

                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Ya tienes otra caja abierta.',
                    'data' => [
                        'session_id' =>
                        $userOpenSession->id,

                        'cash_register_id' =>
                        $userOpenSession->cash_register_id
                    ]
                ], 422);
            }


            /* 
|--------------------------------------------------------------------------
| CREAR SESIÓN
|--------------------------------------------------------------------------
*/

            $openingAmount = round(
                (float) $validated['opening_amount'],
                2
            );

            $session = CashRegisterSession::create([

                'id_company' =>
                $companyId,

                'branch_id' =>
                $cashRegisterModel->branch_id,

                'cash_register_id' =>
                $cashRegisterModel->id,

                'user_id' =>
                $user->id,

                'opening_amount' =>
                $openingAmount,

                'expected_amount' =>
                $openingAmount,

                'closing_amount' =>
                null,

                'difference' =>
                null,

                'opened_at' =>
                now(),

                'closed_at' =>
                null,

                'status' =>
                'OPEN',

                'opening_note' =>
                $validated['opening_note'] ?? null,

                'closing_note' =>
                null,
            ]);

            DB::commit();

            /*
            |--------------------------------------------------------------------------
            | CARGAR RELACIONES
            |--------------------------------------------------------------------------
            */

            $session->load([
                'user:id,name,email',
                'cashRegister.branch',
                'cashRegister.pointOfSale'
            ]);

            /*
            |--------------------------------------------------------------------------
            | RESUMEN
            |--------------------------------------------------------------------------
            */

            $summary = $this->calculateExpectedAmount($session);

            $session->refresh();

            $session->load([
                'user:id,name,email',
                'cashRegister.branch',
                'cashRegister.pointOfSale'
            ]);

            return response()->json([
                'success' => true,

                'message' =>
                'Caja abierta correctamente.',

                'data' => [
                    'session' => $session,
                    'summary' => $summary
                ]
            ], 201);
        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'No se pudo abrir la caja.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | CERRAR CAJA
    |--------------------------------------------------------------------------
    */

    public function closeSession(Request $request, $cashRegister)
    {
        $authUser = Auth::user();

        if (!$authUser) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.'
            ], 401);
        }

        $companyId = $authUser->id_company;
        $userId = $authUser->id;

        $validated = $request->validate([
            'closing_amount' => [
                'required',
                'numeric',
                'min:0'
            ],

            'closing_note' => [
                'nullable',
                'string',
                'max:1000'
            ],
        ]);

        DB::beginTransaction();

        try {

            /*
            |--------------------------------------------------------------------------
            | CAJA
            |--------------------------------------------------------------------------
            */

            $cashRegisterModel = CashRegister::where(
                'id',
                $cashRegister
            )
                ->where(
                    'id_company',
                    $companyId
                )
                ->first();

            if (!$cashRegisterModel) {
                throw new \Exception(
                    'La caja no existe o no pertenece a la empresa.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | SESIÓN DEL USUARIO
            |--------------------------------------------------------------------------
            */

            $session = CashRegisterSession::where(
                'cash_register_id',
                $cashRegisterModel->id
            )
                ->where(
                    'id_company',
                    $companyId
                )
                ->where(
                    'user_id',
                    $userId
                )
                ->where(
                    'status',
                    'OPEN'
                )
                ->lockForUpdate()
                ->first();

            if (!$session) {
                throw new \Exception(
                    'Esta caja no tiene una sesión abierta para el usuario actual.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | CALCULAR RESUMEN
            |--------------------------------------------------------------------------
            */

            $summary =
                $this->calculateExpectedAmount($session);

            /*
            |--------------------------------------------------------------------------
            | EFECTIVO CONTADO
            |--------------------------------------------------------------------------
            */

            $closingAmount = round(
                (float) $validated['closing_amount'],
                2
            );

            /*
            |--------------------------------------------------------------------------
            | EFECTIVO ESPERADO
            |--------------------------------------------------------------------------
            */

            $expectedAmount = round(
                (float) $summary['cash_expected'],
                2
            );

            /*
            |--------------------------------------------------------------------------
            | DIFERENCIA
            |--------------------------------------------------------------------------
            */

            $difference = round(
                $closingAmount -
                    $expectedAmount,
                2
            );

            /*
            |--------------------------------------------------------------------------
            | CERRAR
            |--------------------------------------------------------------------------
            */

            $session->update([

                'closing_amount' =>
                $closingAmount,

                'expected_amount' =>
                $expectedAmount,

                'difference' =>
                $difference,

                'closed_at' =>
                now(),

                'status' =>
                'CLOSED',

                'closing_note' =>
                $validated['closing_note'] ?? null,
            ]);

            $session->refresh();

            $session->load([
                'user:id,name,email',
                'cashRegister.branch',
                'cashRegister.pointOfSale'
            ]);

            DB::commit();

            return response()->json([
                'success' => true,

                'message' =>
                'Caja cerrada correctamente.',

                'data' => [

                    'session' =>
                    $session,

                    'summary' =>
                    $summary,

                    'closing' => [

                        'counted' =>
                        $closingAmount,

                        'expected' =>
                        $expectedAmount,

                        'difference' =>
                        $difference
                    ]
                ]
            ]);
        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'No se pudo cerrar la caja.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | CALCULAR RESUMEN COMPLETO
    |--------------------------------------------------------------------------
    */
/**
 * --------------------------------------------------------------------------
 * CALCULAR RESUMEN COMPLETO DE CAJA
 * --------------------------------------------------------------------------
 *
 * REGLA PRINCIPAL:
 *
 * CAJA FÍSICA ESPERADA =
 *
 * Fondo inicial
 * + Ventas en efectivo
 * + Ingresos de efectivo
 * - Salidas de efectivo
 *
 * IMPORTANTE:
 *
 * QR
 * TARJETA
 * TRANSFERENCIA
 * CHEQUE
 * OTROS
 *
 * NO forman parte del dinero físico esperado.
 */
private function calculateExpectedAmount(
    CashRegisterSession $session
): array {

    /*
    |--------------------------------------------------------------------------
    | FONDO INICIAL
    |--------------------------------------------------------------------------
    */

    $opening = round(
        (float) $session->opening_amount,
        2
    );


    /*
    |--------------------------------------------------------------------------
    | INGRESOS DE EFECTIVO
    |--------------------------------------------------------------------------
    |
    | Solamente movimientos de ingreso de esta sesión.
    |
    */

    $cashIn = CashMovement::query()
        ->where(
            'cash_register_session_id',
            $session->id
        )
        ->where(
            'id_company',
            $session->id_company
        )
        ->where(
            'type',
            'INGRESO'
        )
        ->sum('amount');

    $cashIn = round(
        (float) $cashIn,
        2
    );


    /*
    |--------------------------------------------------------------------------
    | SALIDAS DE EFECTIVO
    |--------------------------------------------------------------------------
    */

    $cashOut = CashMovement::query()
        ->where(
            'cash_register_session_id',
            $session->id
        )
        ->where(
            'id_company',
            $session->id_company
        )
        ->where(
            'type',
            'EGRESO'
        )
        ->sum('amount');

    $cashOut = round(
        (float) $cashOut,
        2
    );


    /*
    |--------------------------------------------------------------------------
    | PAGOS DE VENTAS DE ESTA SESIÓN
    |--------------------------------------------------------------------------
    */

    $salePayments = SalePayment::query()
        ->where(
            'cash_register_session_id',
            $session->id
        )
        ->where(
            'id_company',
            $session->id_company
        )
        ->get();


    /*
    |--------------------------------------------------------------------------
    | AGRUPAR POR MÉTODO DE PAGO
    |--------------------------------------------------------------------------
    */

    $paymentTotals = $salePayments
        ->groupBy(function ($payment) {

            return strtoupper(
                trim($payment->payment_method)
            );

        })
        ->map(function ($payments) {

            return round(
                (float) $payments->sum('amount'),
                2
            );

        });


    /*
    |--------------------------------------------------------------------------
    | VENTAS EN EFECTIVO
    |--------------------------------------------------------------------------
    */

    $cash = round(
        (float) (
            $paymentTotals['CASH'] ?? 0
        ),
        2
    );


    /*
    |--------------------------------------------------------------------------
    | TARJETA
    |--------------------------------------------------------------------------
    */

    $card = round(
        (float) (
            $paymentTotals['CARD'] ?? 0
        ),
        2
    );


    /*
    |--------------------------------------------------------------------------
    | QR
    |--------------------------------------------------------------------------
    */

    $qr = round(
        (float) (
            $paymentTotals['QR'] ?? 0
        ),
        2
    );


    /*
    |--------------------------------------------------------------------------
    | TRANSFERENCIA
    |--------------------------------------------------------------------------
    */

    $transfer = round(
        (float) (
            $paymentTotals['TRANSFER'] ?? 0
        ),
        2
    );


    /*
    |--------------------------------------------------------------------------
    | CHEQUE
    |--------------------------------------------------------------------------
    */

    $check = round(
        (float) (
            $paymentTotals['CHECK'] ?? 0
        ),
        2
    );


    /*
    |--------------------------------------------------------------------------
    | OTROS
    |--------------------------------------------------------------------------
    */

    $other = round(
        (float) (
            $paymentTotals['OTHER'] ?? 0
        ),
        2
    );


    /*
    |--------------------------------------------------------------------------
    | TOTAL DE VENTAS
    |--------------------------------------------------------------------------
    */

    $totalSalesPayments = round(
        $cash +
        $card +
        $qr +
        $transfer +
        $check +
        $other,
        2
    );


    /*
    |--------------------------------------------------------------------------
    | TOTAL DE VENTAS NO FÍSICAS
    |--------------------------------------------------------------------------
    |
    | Esto sirve solamente para mostrar cuánto se cobró mediante
    | medios que NO están físicamente dentro de la caja.
    |
    */

    $nonCashSales = round(
        $card +
        $qr +
        $transfer +
        $check +
        $other,
        2
    );


    /*
    |--------------------------------------------------------------------------
    | CAJA FÍSICA ESPERADA
    |--------------------------------------------------------------------------
    |
    | ESTA ES LA CIFRA IMPORTANTE PARA EL CIERRE.
    |
    | NO SE SUMAN:
    |
    | - TARJETA
    | - QR
    | - TRANSFERENCIA
    | - CHEQUE
    | - OTROS
    |
    */

    $physicalCashExpected = round(
        $opening +
        $cash +
        $cashIn -
        $cashOut,
        2
    );


    /*
    |--------------------------------------------------------------------------
    | ACTUALIZAR SESIÓN
    |--------------------------------------------------------------------------
    |
    | expected_amount representa exclusivamente
    | el efectivo físico esperado.
    |
    */

    $session->update([
        'expected_amount' => $physicalCashExpected,
    ]);


    /*
    |--------------------------------------------------------------------------
    | MÉTODOS DE PAGO PARA LA TABLA
    |--------------------------------------------------------------------------
    */

    $payments = [];


    /*
    |--------------------------------------------------------------------------
    | EFECTIVO
    |--------------------------------------------------------------------------
    */

    if ($cash > 0) {

        $account = null;

        if ($session->cashRegister) {

            $session->cashRegister->loadMissing(
                'accountingAccount.accountType'
            );

            $account =
                $session->cashRegister->accountingAccount;
        }

        $payments[] = [

            'payment_method' => 'CASH',

            'label' => 'Efectivo',

            'amount' => $cash,

            'is_physical_cash' => true,

            'accounting_account' => $account
                ? [
                    'id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->name,

                    'account_type' =>
                        $account->accountType
                            ? [
                                'code' =>
                                    $account->accountType->code,

                                'name' =>
                                    $account->accountType->name,
                            ]
                            : null,
                ]
                : null,
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | TARJETA
    |--------------------------------------------------------------------------
    */

    if ($card > 0) {

        $payments[] = $this->buildPaymentSummary(
            $session->id_company,
            'CARD',
            'Tarjeta',
            $card
        );
    }


    /*
    |--------------------------------------------------------------------------
    | QR
    |--------------------------------------------------------------------------
    */

    if ($qr > 0) {

        $payments[] = $this->buildPaymentSummary(
            $session->id_company,
            'QR',
            'QR',
            $qr
        );
    }


    /*
    |--------------------------------------------------------------------------
    | TRANSFERENCIA
    |--------------------------------------------------------------------------
    */

    if ($transfer > 0) {

        $payments[] = $this->buildPaymentSummary(
            $session->id_company,
            'TRANSFER',
            'Transferencia',
            $transfer
        );
    }


    /*
    |--------------------------------------------------------------------------
    | CHEQUE
    |--------------------------------------------------------------------------
    */

    if ($check > 0) {

        $payments[] = $this->buildPaymentSummary(
            $session->id_company,
            'CHECK',
            'Cheque',
            $check
        );
    }


    /*
    |--------------------------------------------------------------------------
    | OTROS
    |--------------------------------------------------------------------------
    */

    if ($other > 0) {

        $payments[] = $this->buildPaymentSummary(
            $session->id_company,
            'OTHER',
            'Otros',
            $other
        );
    }


    /*
    |--------------------------------------------------------------------------
    | RESULTADO
    |--------------------------------------------------------------------------
    */

    return [

        /*
        |--------------------------------------------------------------------------
        | FONDO INICIAL
        |--------------------------------------------------------------------------
        */

        'opening_amount' => $opening,


        /*
        |--------------------------------------------------------------------------
        | CAJA FÍSICA
        |--------------------------------------------------------------------------
        */

        'physical_cash' => [

            'opening' => $opening,

            'cash_sales' => $cash,

            'cash_in' => $cashIn,

            'cash_out' => $cashOut,

            'expected' => $physicalCashExpected,
        ],


        /*
        |--------------------------------------------------------------------------
        | VENTAS
        |--------------------------------------------------------------------------
        */

        'sales' => [

            'cash' => $cash,

            'card' => $card,

            'qr' => $qr,

            'transfer' => $transfer,

            'check' => $check,

            'other' => $other,

            'non_cash' => $nonCashSales,

            'total' => $totalSalesPayments,
        ],


        /*
        |--------------------------------------------------------------------------
        | COMPATIBILIDAD CON TU VUE ACTUAL
        |--------------------------------------------------------------------------
        */

        'cash_sales' => $cash,

        'card_sales' => $card,

        'qr_sales' => $qr,

        'transfer_sales' => $transfer,

        'check_sales' => $check,

        'balance_sales' => 0,

        'other_sales' => $other,


        /*
        |--------------------------------------------------------------------------
        | MOVIMIENTOS DE EFECTIVO
        |--------------------------------------------------------------------------
        */

        'other_income' => $cashIn,

        'expenses' => $cashOut,


        /*
        |--------------------------------------------------------------------------
        | TOTALES
        |--------------------------------------------------------------------------
        */

        'total_sales_payments' => $totalSalesPayments,

        'non_cash_sales' => $nonCashSales,


        /*
        |--------------------------------------------------------------------------
        | EFECTIVO FÍSICO ESPERADO
        |--------------------------------------------------------------------------
        */

        'cash_expected' => $physicalCashExpected,

        'expected_cash' => $physicalCashExpected,

        'physical_cash_expected' => $physicalCashExpected,


        /*
        |--------------------------------------------------------------------------
        | MÉTODOS DE PAGO
        |--------------------------------------------------------------------------
        */

        'payments' => $payments,
    ];
}

/// medo
private function buildPaymentSummary(
    string $companyId,
    string $paymentMethod,
    string $label,
    float $amount
): array {

    $mapping = AccountingPaymentMapping::query()
        ->where(
            'id_company',
            $companyId
        )
        ->where(
            'payment_method',
            $paymentMethod
        )
        ->where(
            'status',
            true
        )
        ->with(
            'accountingAccount.accountType'
        )
        ->first();


    $account = $mapping
        ? $mapping->accountingAccount
        : null;


    return [

        'payment_method' =>
            $paymentMethod,

        'label' =>
            $label,

        'amount' =>
            round($amount, 2),

        'accounting_account' =>
            $account
                ? [

                    'id' =>
                        $account->id,

                    'code' =>
                        $account->code,

                    'name' =>
                        $account->name,

                    'account_type' =>
                        $account->accountType
                            ? [

                                'code' =>
                                    $account->accountType->code,

                                'name' =>
                                    $account->accountType->name,

                            ]
                            : null,

                ]
                : null,
    ];
}

}
