<?php

namespace App\Domains\Contabilidad\Services;

use Illuminate\Support\Facades\DB;

class FinancialStatementService
{
    /**
     * ============================================================
     * ESTADO DE RESULTADOS
     * ============================================================
     *
     * INGRESOS
     * GASTOS
     * UTILIDAD / PÉRDIDA
     */
    public function incomeStatement(
        string $idCompany,
        string $startDate,
        string $endDate
    ): array {

        $accounts = DB::table('accounting_accounts as a')

            ->join(
                'accounting_entry_details as d',
                'd.accounting_account_id',
                '=',
                'a.id'
            )

            ->join(
                'accounting_entries as e',
                'e.id',
                '=',
                'd.accounting_entry_id'
            )

            ->where(
                'a.id_company',
                $idCompany
            )

            ->where(
                'e.id_company',
                $idCompany
            )

            ->where(
                'e.status',
                'POSTED'
            )

            ->whereDate(
                'e.date',
                '>=',
                $startDate
            )

            ->whereDate(
                'e.date',
                '<=',
                $endDate
            )

            ->whereIn(
                DB::raw('UPPER(a.type)'),
                [
                    'INGRESO',
                    'INCOME',
                    'VENTA',
                    'GASTO',
                    'EXPENSE'
                ]
            )

            ->select([
                'a.id',
                'a.code',
                'a.name',
                'a.type',
                'a.level',
                'a.parent_id',
            ])

            ->selectRaw(
                'COALESCE(SUM(d.debit), 0) as debit'
            )

            ->selectRaw(
                'COALESCE(SUM(d.credit), 0) as credit'
            )

            ->groupBy([
                'a.id',
                'a.code',
                'a.name',
                'a.type',
                'a.level',
                'a.parent_id',
            ])

            ->orderBy('a.code')

            ->get();


        $income = [];

        $expenses = [];


        foreach ($accounts as $account) {

            $debit = (float) $account->debit;

            $credit = (float) $account->credit;

            $type = strtoupper(
                trim($account->type)
            );


            /*
            |--------------------------------------------------------------------------
            | INGRESOS
            |--------------------------------------------------------------------------
            */

            if (
                in_array(
                    $type,
                    [
                        'INGRESO',
                        'INCOME',
                        'VENTA'
                    ],
                    true
                )
            ) {

                $amount = $credit - $debit;

                if (abs($amount) > 0.001) {

                    $income[] = [
                        'id' => $account->id,
                        'code' => $account->code,
                        'name' => $account->name,
                        'type' => $account->type,
                        'level' => $account->level,
                        'parent_id' => $account->parent_id,

                        'debit' => round(
                            $debit,
                            2
                        ),

                        'credit' => round(
                            $credit,
                            2
                        ),

                        'amount' => round(
                            $amount,
                            2
                        ),
                    ];
                }
            }


            /*
            |--------------------------------------------------------------------------
            | GASTOS
            |--------------------------------------------------------------------------
            */

            if (
                in_array(
                    $type,
                    [
                        'GASTO',
                        'EXPENSE'
                    ],
                    true
                )
            ) {

                $amount = $debit - $credit;

                if (abs($amount) > 0.001) {

                    $expenses[] = [
                        'id' => $account->id,
                        'code' => $account->code,
                        'name' => $account->name,
                        'type' => $account->type,
                        'level' => $account->level,
                        'parent_id' => $account->parent_id,

                        'debit' => round(
                            $debit,
                            2
                        ),

                        'credit' => round(
                            $credit,
                            2
                        ),

                        'amount' => round(
                            $amount,
                            2
                        ),
                    ];
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | TOTALES
        |--------------------------------------------------------------------------
        */

        $totalIncome = collect($income)
            ->sum('amount');

        $totalExpenses = collect($expenses)
            ->sum('amount');

        $netIncome =
            $totalIncome -
            $totalExpenses;


        return [

            'income' => $income,

            'expenses' => $expenses,

            'total_income' => round(
                $totalIncome,
                2
            ),

            'total_expenses' => round(
                $totalExpenses,
                2
            ),

            'net_income' => round(
                $netIncome,
                2
            ),

            'start_date' => $startDate,

            'end_date' => $endDate,
        ];
    }


    /**
     * ============================================================
     * BALANCE GENERAL
     * ============================================================
     *
     * ACTIVO
     * PASIVO
     * PATRIMONIO
     */
    public function balanceSheet(
        string $idCompany,
        string $startDate,
        string $endDate
    ): array {

        /*
        |--------------------------------------------------------------------------
        | SALDOS ACUMULADOS HASTA LA FECHA
        |--------------------------------------------------------------------------
        |
        | El Balance General es acumulativo.
        |
        */

        $accounts = DB::table(
            'accounting_accounts as a'
        )

            ->join(
                'accounting_entry_details as d',
                'd.accounting_account_id',
                '=',
                'a.id'
            )

            ->join(
                'accounting_entries as e',
                'e.id',
                '=',
                'd.accounting_entry_id'
            )

            ->where(
                'a.id_company',
                $idCompany
            )

            ->where(
                'e.id_company',
                $idCompany
            )

            ->where(
                'e.status',
                'POSTED'
            )

            ->whereDate(
                'e.date',
                '<=',
                $endDate
            )

            ->whereIn(
                DB::raw('UPPER(a.type)'),
                [
                    'ACTIVO',
                    'ASSET',
                    'PASIVO',
                    'LIABILITY',
                    'PATRIMONIO',
                    'EQUITY'
                ]
            )

            ->select([
                'a.id',
                'a.code',
                'a.name',
                'a.type',
                'a.level',
                'a.parent_id',
            ])

            ->selectRaw(
                'COALESCE(SUM(d.debit), 0) as debit'
            )

            ->selectRaw(
                'COALESCE(SUM(d.credit), 0) as credit'
            )

            ->groupBy([
                'a.id',
                'a.code',
                'a.name',
                'a.type',
                'a.level',
                'a.parent_id',
            ])

            ->orderBy('a.code')

            ->get();


        $assets = [];

        $liabilities = [];

        $equity = [];


        foreach ($accounts as $account) {

            $debit = (float) $account->debit;

            $credit = (float) $account->credit;

            $type = strtoupper(
                trim($account->type)
            );


            /*
            |--------------------------------------------------------------------------
            | ACTIVO
            |--------------------------------------------------------------------------
            */

            if (
                in_array(
                    $type,
                    [
                        'ACTIVO',
                        'ASSET'
                    ],
                    true
                )
            ) {

                $balance =
                    $debit -
                    $credit;

                if (abs($balance) > 0.001) {

                    $assets[] = [
                        'id' => $account->id,
                        'code' => $account->code,
                        'name' => $account->name,
                        'type' => $account->type,
                        'level' => $account->level,
                        'parent_id' => $account->parent_id,

                        'debit' => round(
                            $debit,
                            2
                        ),

                        'credit' => round(
                            $credit,
                            2
                        ),

                        'amount' => round(
                            $balance,
                            2
                        ),
                    ];
                }
            }


            /*
            |--------------------------------------------------------------------------
            | PASIVO
            |--------------------------------------------------------------------------
            */

            if (
                in_array(
                    $type,
                    [
                        'PASIVO',
                        'LIABILITY'
                    ],
                    true
                )
            ) {

                $balance =
                    $credit -
                    $debit;

                if (abs($balance) > 0.001) {

                    $liabilities[] = [
                        'id' => $account->id,
                        'code' => $account->code,
                        'name' => $account->name,
                        'type' => $account->type,
                        'level' => $account->level,
                        'parent_id' => $account->parent_id,

                        'debit' => round(
                            $debit,
                            2
                        ),

                        'credit' => round(
                            $credit,
                            2
                        ),

                        'amount' => round(
                            $balance,
                            2
                        ),
                    ];
                }
            }


            /*
            |--------------------------------------------------------------------------
            | PATRIMONIO
            |--------------------------------------------------------------------------
            */

            if (
                in_array(
                    $type,
                    [
                        'PATRIMONIO',
                        'EQUITY'
                    ],
                    true
                )
            ) {

                $balance =
                    $credit -
                    $debit;

                if (abs($balance) > 0.001) {

                    $equity[] = [
                        'id' => $account->id,
                        'code' => $account->code,
                        'name' => $account->name,
                        'type' => $account->type,
                        'level' => $account->level,
                        'parent_id' => $account->parent_id,

                        'debit' => round(
                            $debit,
                            2
                        ),

                        'credit' => round(
                            $credit,
                            2
                        ),

                        'amount' => round(
                            $balance,
                            2
                        ),
                    ];
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | TOTALES
        |--------------------------------------------------------------------------
        */

        $totalAssets = collect($assets)
            ->sum('amount');

        $totalLiabilities = collect($liabilities)
            ->sum('amount');

        $totalEquityAccounts = collect($equity)
            ->sum('amount');


        /*
        |--------------------------------------------------------------------------
        | RESULTADO DEL PERÍODO
        |--------------------------------------------------------------------------
        */

        $incomeStatement = $this->incomeStatement(
            $idCompany,
            $startDate,
            $endDate
        );

        $currentResult =
            (float) $incomeStatement['net_income'];


        /*
        |--------------------------------------------------------------------------
        | PATRIMONIO TOTAL
        |--------------------------------------------------------------------------
        */

        $totalEquity =
            $totalEquityAccounts +
            $currentResult;


        /*
        |--------------------------------------------------------------------------
        | PASIVO + PATRIMONIO
        |--------------------------------------------------------------------------
        */

        $totalLiabilitiesEquity =
            $totalLiabilities +
            $totalEquity;


        /*
        |--------------------------------------------------------------------------
        | DIFERENCIA
        |--------------------------------------------------------------------------
        */

        $difference =
            $totalAssets -
            $totalLiabilitiesEquity;


        return [

            'assets' => $assets,

            'liabilities' => $liabilities,

            'equity' => $equity,

            'current_result' => round(
                $currentResult,
                2
            ),

            'total_assets' => round(
                $totalAssets,
                2
            ),

            'total_liabilities' => round(
                $totalLiabilities,
                2
            ),

            'total_equity' => round(
                $totalEquity,
                2
            ),

            'total_liabilities_equity' => round(
                $totalLiabilitiesEquity,
                2
            ),

            'difference' => round(
                $difference,
                2
            ),

            'start_date' => $startDate,

            'end_date' => $endDate,
        ];
    }
}