<?php

namespace App\Domains\Caja\Controllers\card_ingresos_egresos;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use App\Domains\Caja\Models\CashRegisterSession;
use App\Domains\Ventas\Models\SaleReturn;

class CardIngresosEgresos extends Controller
{
    /**
     * ============================================================
     * OBTENER RESUMEN DE CAJA
     * ============================================================
     *
     * PAGOS DE VENTAS:
     *
     * sale_payments.is_collected = true
     *      => pago cobrado/confirmado
     *
     * sale_payments.is_collected = false
     *      => pago pendiente
     *
     * CASH:
     *      afecta el efectivo físico de la caja.
     *
     * QR / CARD / TRANSFER / OTHER:
     *      forman parte del total cobrado,
     *      pero NO aumentan el efectivo físico.
     *
     * CASH_MOVEMENTS:
     *      se utilizan para ingresos y egresos propios
     *      de la caja.
     *
     */
    public function getCajaIngresosEgresos(Request $request)
    {
        try {

            // =====================================================
            // USUARIO AUTENTICADO
            // =====================================================

            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado.',
                ], 401);
            }

            $idCompany = $user->id_company;

            if (!$idCompany) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario no tiene una empresa asignada.',
                ], 422);
            }


            // =====================================================
            // SESIÓN ABIERTA
            // =====================================================

            $session = CashRegisterSession::with([
                'cashRegister',
                'branch',
                'user',

                // Movimientos propios de caja
                'movements',

                // PAGOS DE VENTAS
                'salePayments',

                // Movimientos relacionados a pagos
                'movements.salePayment',

                // Movimientos relacionados a devoluciones
                'movements.saleReturn',
            ])
                ->where('id_company', $idCompany)
                ->where('user_id', $user->id)
                ->where('status', 'OPEN')
                ->latest('id')
                ->first();


            // =====================================================
            // NO EXISTE CAJA ABIERTA
            // =====================================================

            if (!$session) {

                return response()->json([

                    'success' => true,

                    'session_open' => false,

                    'session' => null,

                    'summary' => [

                        'opening_amount' => 0,

                        'total_ingresos' => 0,

                        'total_egresos' => 0,

                        'total_devoluciones' => 0,

                        'saldo_movimientos' => 0,

                        'saldo_caja' => 0,

                        'cash_sales' => 0,

                        'other_income' => 0,

                        'expenses' => 0,

                        'returns' => 0,

                        'return_cash' => 0,

                        'return_qr' => 0,

                        'return_card' => 0,

                        'return_transfer' => 0,

                        'return_other' => 0,

                        'physical_cash_expected' => 0,

                        'non_cash_sales' => 0,

                        'total_sales_payments' => 0,

                        'expected_cash' => 0,
                    ],

                    'payment_methods' => [

                        'cash' => 0,

                        'qr' => 0,

                        'card' => 0,

                        'transfer' => 0,

                        'other' => 0,

                        'total' => 0,
                    ],

                    'refund_methods' => [

                        'cash' => 0,

                        'qr' => 0,

                        'card' => 0,

                        'transfer' => 0,

                        'other' => 0,

                        'total' => 0,
                    ],

                    'returns' => [],
                ]);
            }


            // =====================================================
            // MOVIMIENTOS DE CAJA
            // =====================================================

            $movements = $session->movements;


            // =====================================================
            // INGRESOS DE CAJA
            //
            // IMPORTANTE:
            //
            // Aquí NO incluimos sale_payments.
            //
            // Los pagos de venta se calculan directamente
            // desde sale_payments.
            // =====================================================

            $incomeMovements = $movements->filter(function ($movement) {

                return strtoupper(
                    (string) $movement->type
                ) === 'INGRESO';

            });


            $totalIngresos = $incomeMovements->sum(function ($movement) {

                return (float) $movement->amount;

            });


            // =====================================================
            // EGRESOS NORMALES
            //
            // Las devoluciones se calculan aparte.
            // =====================================================

            $expenseMovements = $movements->filter(function ($movement) {

                return strtoupper(
                    (string) $movement->type
                ) === 'EGRESO'
                && is_null($movement->sale_return_id);

            });


            $totalEgresos = $expenseMovements->sum(function ($movement) {

                return (float) $movement->amount;

            });


            // =====================================================
            // PAGOS DE VENTAS
            //
            // AHORA LA FUENTE ES sale_payments
            //
            // SOLO:
            //
            // is_collected = true
            //
            // =====================================================

            $collectedSalePayments = $session->salePayments
                ->filter(function ($payment) {

                    return (bool) $payment->is_collected === true;

                });


            // =====================================================
            // EFECTIVO
            // =====================================================

            $totalCash = $collectedSalePayments
                ->filter(function ($payment) {

                    return strtoupper(
                        (string) $payment->payment_method
                    ) === 'CASH';

                })
                ->sum(function ($payment) {

                    return (float) $payment->amount;

                });


            // =====================================================
            // QR
            // =====================================================

            $totalQr = $collectedSalePayments
                ->filter(function ($payment) {

                    return strtoupper(
                        (string) $payment->payment_method
                    ) === 'QR';

                })
                ->sum(function ($payment) {

                    return (float) $payment->amount;

                });


            // =====================================================
            // TARJETA
            // =====================================================

            $totalCard = $collectedSalePayments
                ->filter(function ($payment) {

                    return strtoupper(
                        (string) $payment->payment_method
                    ) === 'CARD';

                })
                ->sum(function ($payment) {

                    return (float) $payment->amount;

                });


            // =====================================================
            // TRANSFERENCIA
            // =====================================================

            $totalTransfer = $collectedSalePayments
                ->filter(function ($payment) {

                    return strtoupper(
                        (string) $payment->payment_method
                    ) === 'TRANSFER';

                })
                ->sum(function ($payment) {

                    return (float) $payment->amount;

                });


            // =====================================================
            // OTROS MÉTODOS
            // =====================================================

            $totalOther = $collectedSalePayments
                ->filter(function ($payment) {

                    $method = strtoupper(
                        (string) $payment->payment_method
                    );

                    return !in_array($method, [
                        'CASH',
                        'QR',
                        'CARD',
                        'TRANSFER',
                    ]);

                })
                ->sum(function ($payment) {

                    return (float) $payment->amount;

                });


            // =====================================================
            // TOTAL DE VENTAS COBRADAS
            // =====================================================

            $totalVentas =
                (float) $totalCash
                + (float) $totalQr
                + (float) $totalCard
                + (float) $totalTransfer
                + (float) $totalOther;


            // =====================================================
            // OTROS INGRESOS
            //
            // Son ingresos que NO pertenecen a una venta.
            // =====================================================

            $otherIncome = $incomeMovements
                ->filter(function ($movement) {

                    return is_null($movement->sale_payment_id)
                        && is_null($movement->sale_return_id);

                })
                ->sum(function ($movement) {

                    return (float) $movement->amount;

                });


            // =====================================================
            // DEVOLUCIONES
            // =====================================================
            //
            // Aquí mantenemos la lógica de devoluciones asociadas
            // a la sesión.
            //
            // =====================================================

            $saleReturns = SaleReturn::with([
                'sale',
                'client',
                'user',
                'branch',
                'cashRegister',
                'cashRegisterSession',
            ])
                ->where('id_company', $idCompany)
                ->where(
                    'cash_register_session_id',
                    $session->id
                )
                ->orderByDesc('id')
                ->get();


            // =====================================================
            // TOTAL DEVOLUCIONES
            // =====================================================

            $totalDevoluciones = $saleReturns->sum(function ($return) {

                return (float) $return->total_amount;

            });


            // =====================================================
            // DEVOLUCIONES CASH
            // =====================================================

            $totalReturnCash = $saleReturns
                ->filter(function ($return) {

                    return strtoupper(
                        (string) $return->refund_method
                    ) === 'CASH';

                })
                ->sum(function ($return) {

                    return (float) $return->total_amount;

                });


            // =====================================================
            // DEVOLUCIONES QR
            // =====================================================

            $totalReturnQr = $saleReturns
                ->filter(function ($return) {

                    return strtoupper(
                        (string) $return->refund_method
                    ) === 'QR';

                })
                ->sum(function ($return) {

                    return (float) $return->total_amount;

                });


            // =====================================================
            // DEVOLUCIONES CARD
            // =====================================================

            $totalReturnCard = $saleReturns
                ->filter(function ($return) {

                    return strtoupper(
                        (string) $return->refund_method
                    ) === 'CARD';

                })
                ->sum(function ($return) {

                    return (float) $return->total_amount;

                });


            // =====================================================
            // DEVOLUCIONES TRANSFERENCIA
            // =====================================================

            $totalReturnTransfer = $saleReturns
                ->filter(function ($return) {

                    return strtoupper(
                        (string) $return->refund_method
                    ) === 'TRANSFER';

                })
                ->sum(function ($return) {

                    return (float) $return->total_amount;

                });


            // =====================================================
            // DEVOLUCIONES OTROS
            // =====================================================

            $totalReturnOther = $saleReturns
                ->filter(function ($return) {

                    $method = strtoupper(
                        (string) $return->refund_method
                    );

                    return !in_array($method, [
                        'CASH',
                        'QR',
                        'CARD',
                        'TRANSFER',
                    ]);

                })
                ->sum(function ($return) {

                    return (float) $return->total_amount;

                });


            // =====================================================
            // FONDO INICIAL
            // =====================================================

            $openingAmount = (float) $session->opening_amount;


            // =====================================================
            // EFECTIVO FÍSICO ESPERADO
            // =====================================================
            //
            // IMPORTANTE:
            //
            // Solo CASH afecta físicamente la caja.
            //
            // APERTURA
            // + VENTAS CASH
            // + OTROS INGRESOS
            // - EGRESOS
            // - DEVOLUCIONES CASH
            //
            // QR, CARD y TRANSFER NO entran aquí.
            //
            // =====================================================

            $physicalCashExpected =
                $openingAmount
                + (float) $totalCash
                + (float) $otherIncome
                - (float) $totalEgresos
                - (float) $totalReturnCash;


            // =====================================================
            // SALDO DE MOVIMIENTOS
            // =====================================================

            $saldoMovimientos =
                (float) $totalIngresos
                - (float) $totalEgresos
                - (float) $totalDevoluciones;


            // =====================================================
            // SALDO DE CAJA
            // =====================================================

            $saldoCaja = $physicalCashExpected;


            // =====================================================
            // VENTAS NO EFECTIVO
            // =====================================================

            $nonCashSales =
                (float) $totalQr
                + (float) $totalCard
                + (float) $totalTransfer
                + (float) $totalOther;


            // =====================================================
            // DETALLE DE DEVOLUCIONES
            // =====================================================

            $returns = $saleReturns
                ->map(function ($return) {

                    $refundMethod = strtoupper(
                        (string) $return->refund_method
                    );


                    $methodLabel = match ($refundMethod) {

                        'CASH' => 'Efectivo',

                        'QR' => 'QR',

                        'CARD' => 'Tarjeta',

                        'TRANSFER' => 'Transferencia',

                        'OTHER' => 'Otros',

                        default => 'Otros',

                    };


                    return [

                        'id' =>
                            $return->id,

                        'movement_id' =>
                            null,

                        'sale_return_id' =>
                            $return->id,

                        'code_return' =>
                            $return->code_return,

                        'sale_id' =>
                            $return->sale_id,

                        'client_id' =>
                            $return->client_id,

                        'refund_type' =>
                            $return->refund_type,

                        'refund_method' =>
                            $refundMethod,

                        'refund_method_label' =>
                            $methodLabel,

                        'reason' =>
                            $return->reason,

                        'amount' =>
                            (float) $return->total_amount,

                        'status' =>
                            $return->status,

                        'date' =>
                            $return->date,

                        'description' =>
                            $return->reason,
                    ];
                })
                ->values();


            // =====================================================
            // RESPUESTA
            // =====================================================

            return response()->json([

                'success' => true,

                'session_open' => true,


                // =================================================
                // SESIÓN
                // =================================================

                'session' => [

                    'id' =>
                        $session->id,

                    'status' =>
                        $session->status,

                    'opening_amount' =>
                        (float) $session->opening_amount,

                    'expected_amount' =>
                        (float) $session->expected_amount,

                    'opened_at' =>
                        $session->opened_at,

                    'closed_at' =>
                        $session->closed_at,

                    'opening_note' =>
                        $session->opening_note,

                    'closing_note' =>
                        $session->closing_note,


                    'user' => $session->user
                        ? [

                            'id' =>
                                $session->user->id,

                            'name' =>
                                $session->user->name,

                            'email' =>
                                $session->user->email,

                        ]
                        : null,


                    'branch' => $session->branch
                        ? [

                            'id' =>
                                $session->branch->id,

                            'code_branch' =>
                                $session->branch->code_branch,

                            'branch_name' =>
                                $session->branch->branch_name,

                        ]
                        : null,


                    'cash_register' =>
                        $session->cashRegister
                            ? [

                                'id' =>
                                    $session->cashRegister->id,

                                'name' =>
                                    $session->cashRegister->name,

                                'code' =>
                                    $session->cashRegister->code,

                                'status' =>
                                    $session->cashRegister->status,

                            ]
                            : null,
                ],


                // =================================================
                // RESUMEN
                // =================================================

                'summary' => [

                    'opening_amount' =>
                        $openingAmount,

                    'total_ingresos' =>
                        (float) $totalIngresos,

                    'total_egresos' =>
                        (float) $totalEgresos,

                    'total_devoluciones' =>
                        (float) $totalDevoluciones,

                    'saldo_movimientos' =>
                        (float) $saldoMovimientos,

                    'saldo_caja' =>
                        (float) $saldoCaja,

                    'cash_sales' =>
                        (float) $totalCash,

                    'other_income' =>
                        (float) $otherIncome,

                    'expenses' =>
                        (float) $totalEgresos,

                    'returns' =>
                        (float) $totalDevoluciones,

                    'return_cash' =>
                        (float) $totalReturnCash,

                    'return_qr' =>
                        (float) $totalReturnQr,

                    'return_card' =>
                        (float) $totalReturnCard,

                    'return_transfer' =>
                        (float) $totalReturnTransfer,

                    'return_other' =>
                        (float) $totalReturnOther,

                    'physical_cash_expected' =>
                        (float) $physicalCashExpected,

                    'non_cash_sales' =>
                        (float) $nonCashSales,

                    'total_sales_payments' =>
                        (float) $totalVentas,

                    'expected_cash' =>
                        (float) $physicalCashExpected,
                ],


                // =================================================
                // MÉTODOS DE PAGO
                // =================================================

                'payment_methods' => [

                    'cash' =>
                        (float) $totalCash,

                    'qr' =>
                        (float) $totalQr,

                    'card' =>
                        (float) $totalCard,

                    'transfer' =>
                        (float) $totalTransfer,

                    'other' =>
                        (float) $totalOther,

                    'total' =>
                        (float) $totalVentas,
                ],


                // =================================================
                // DEVOLUCIONES
                // =================================================

                'refund_methods' => [

                    'cash' =>
                        (float) $totalReturnCash,

                    'qr' =>
                        (float) $totalReturnQr,

                    'card' =>
                        (float) $totalReturnCard,

                    'transfer' =>
                        (float) $totalReturnTransfer,

                    'other' =>
                        (float) $totalReturnOther,

                    'total' =>
                        (float) $totalDevoluciones,
                ],


                // =================================================
                // DETALLE
                // =================================================

                'returns' =>
                    $returns,
            ]);


        } catch (\Throwable $e) {

            return response()->json([

                'success' => false,

                'message' =>
                    'Error al obtener la información de caja.',

                'error' =>
                    $e->getMessage(),

            ], 500);
        }
    }
}