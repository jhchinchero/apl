<?php

namespace App\Domains\Contabilidad\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AccountingLedgerService
{
    public function getLedger(array $filters = [])
    {
        $user = Auth::user();

        if (!$user) {
            throw new \Exception('Usuario no autenticado.');
        }

        $idCompany = $user->id_company;

        /*
        |--------------------------------------------------------------------------
        | FECHAS
        |--------------------------------------------------------------------------
        */

        $startDate = $filters['start_date']
            ?? now()->startOfYear()->format('Y-m-d');

        $endDate = $filters['end_date']
            ?? now()->format('Y-m-d');

        /*
        |--------------------------------------------------------------------------
        | LISTA DE CUENTAS
        |--------------------------------------------------------------------------
        |
        | Esto alimenta el Dropdown del Vue.
        |
        */

        $accounts = DB::table('accounting_accounts')
            ->where('id_company', $idCompany)
            ->where('status', true)
            ->select([
                'id',
                'code',
                'name',
                'type',
                'level',
                'parent_id',
                'allows_entries',
            ])
            ->orderBy('code')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | SI NO SE SELECCIONÓ CUENTA
        |--------------------------------------------------------------------------
        |
        | En este caso devolvemos las cuentas pero no movimientos.
        |
        */

        if (empty($filters['account_id'])) {

            return [
                'accounts' => $accounts,

                'movements' => [],

                'totals' => [
                    'debit' => 0,
                    'credit' => 0,
                ],

                'opening_balance' => 0,

                'closing_balance' => 0,

                'start_date' => $startDate,

                'end_date' => $endDate,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | CUENTA SELECCIONADA
        |--------------------------------------------------------------------------
        */

        $accountId = $filters['account_id'];

        /*
        |--------------------------------------------------------------------------
        | VERIFICAR QUE LA CUENTA PERTENEZCA A LA EMPRESA
        |--------------------------------------------------------------------------
        */

        $account = DB::table('accounting_accounts')
            ->where('id', $accountId)
            ->where('id_company', $idCompany)
            ->where('status', true)
            ->first();

        if (!$account) {

            return [
                'accounts' => $accounts,

                'movements' => [],

                'totals' => [
                    'debit' => 0,
                    'credit' => 0,
                ],

                'opening_balance' => 0,

                'closing_balance' => 0,

                'start_date' => $startDate,

                'end_date' => $endDate,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | SALDO INICIAL
        |--------------------------------------------------------------------------
        |
        | Todo lo contabilizado ANTES de la fecha inicial.
        |
        */

        $openingQuery = DB::table('accounting_entry_details as d')
            ->join(
                'accounting_entries as e',
                'e.id',
                '=',
                'd.accounting_entry_id'
            )
            ->where(
                'd.accounting_account_id',
                $accountId
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
                '<',
                $startDate
            );

        /*
        |--------------------------------------------------------------------------
        | SUCURSAL PARA SALDO INICIAL
        |--------------------------------------------------------------------------
        */

        if (!empty($filters['branch_id'])) {

            $openingQuery->where(
                'e.branch_id',
                $filters['branch_id']
            );
        }

        $opening = $openingQuery
            ->selectRaw('COALESCE(SUM(d.debit), 0) as debit')
            ->selectRaw('COALESCE(SUM(d.credit), 0) as credit')
            ->first();

        $openingDebit = (float) ($opening->debit ?? 0);
        $openingCredit = (float) ($opening->credit ?? 0);

        /*
        |--------------------------------------------------------------------------
        | SALDO SEGÚN NATURALEZA DE LA CUENTA
        |--------------------------------------------------------------------------
        */

        $openingBalance = $this->calculateBalance(
            $account->type,
            $openingDebit,
            $openingCredit
        );

        /*
        |--------------------------------------------------------------------------
        | MOVIMIENTOS DEL PERÍODO
        |--------------------------------------------------------------------------
        */

        $query = DB::table('accounting_entry_details as d')

            ->join(
                'accounting_entries as e',
                'e.id',
                '=',
                'd.accounting_entry_id'
            )

            ->join(
                'accounting_accounts as a',
                'a.id',
                '=',
                'd.accounting_account_id'
            )

            ->where(
                'e.id_company',
                $idCompany
            )

            ->where(
                'a.id_company',
                $idCompany
            )

            ->where(
                'e.status',
                'POSTED'
            )

            ->where(
                'd.accounting_account_id',
                $accountId
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
            );

        /*
        |--------------------------------------------------------------------------
        | SUCURSAL
        |--------------------------------------------------------------------------
        */

        if (!empty($filters['branch_id'])) {

            $query->where(
                'e.branch_id',
                $filters['branch_id']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | OBTENER MOVIMIENTOS
        |--------------------------------------------------------------------------
        */

        $details = $query
            ->select([
                'd.id',
                'd.accounting_account_id',

                'e.id as entry_id',
                'e.date',
                'e.code_entry',
                'e.type',
                'e.description as entry_description',

                'a.code as account_code',
                'a.name as account_name',
                'a.type as account_type',

                'd.description',
                'd.debit',
                'd.credit',
            ])
            ->orderBy('e.date')
            ->orderBy('e.id')
            ->orderBy('d.id')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | SALDO ACUMULADO
        |--------------------------------------------------------------------------
        */

        $balance = $openingBalance;

        $rows = [];

        foreach ($details as $detail) {

            $debit = (float) ($detail->debit ?? 0);
            $credit = (float) ($detail->credit ?? 0);

            /*
            |--------------------------------------------------------------
            | ACTIVO / GASTO
            |--------------------------------------------------------------
            */

            if (
                in_array(
                    strtoupper($account->type),
                    [
                        'ACTIVO',
                        'ASSET',
                        'GASTO',
                        'EXPENSE',
                    ]
                )
            ) {

                $balance += $debit;
                $balance -= $credit;
            }

            /*
            |--------------------------------------------------------------
            | PASIVO / PATRIMONIO / INGRESO
            |--------------------------------------------------------------
            */

            else {

                $balance += $credit;
                $balance -= $debit;
            }

            $rows[] = [

                'id' => $detail->id,

                'date' => $detail->date,

                'entry_id' => $detail->entry_id,

                'code_entry' => $detail->code_entry,

                'type' => $detail->type,

                'account_id' => $detail->accounting_account_id,

                'account_code' => $detail->account_code,

                'account_name' => $detail->account_name,

                'account_type' => $detail->account_type,

                'description' =>
                    $detail->description
                    ?: $detail->entry_description,

                'debit' => round($debit, 2),

                'credit' => round($credit, 2),

                'balance' => round($balance, 2),
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | TOTALES DEL PERÍODO
        |--------------------------------------------------------------------------
        */

        $totalDebit = $details->sum(
            fn ($item) => (float) $item->debit
        );

        $totalCredit = $details->sum(
            fn ($item) => (float) $item->credit
        );

        /*
        |--------------------------------------------------------------------------
        | SALDO FINAL
        |--------------------------------------------------------------------------
        */

        $closingBalance = $balance;

        /*
        |--------------------------------------------------------------------------
        | RESPUESTA
        |--------------------------------------------------------------------------
        */

        return [

            /*
            |--------------------------------------------------------------
            | CUENTAS PARA EL DROPDOWN
            |--------------------------------------------------------------
            */

            'accounts' => $accounts,

            /*
            |--------------------------------------------------------------
            | MOVIMIENTOS
            |--------------------------------------------------------------
            */

            'movements' => $rows,

            /*
            |--------------------------------------------------------------
            | TOTALES
            |--------------------------------------------------------------
            */

            'totals' => [

                'debit' => round(
                    $totalDebit,
                    2
                ),

                'credit' => round(
                    $totalCredit,
                    2
                ),
            ],

            /*
            |--------------------------------------------------------------
            | SALDOS
            |--------------------------------------------------------------
            */

            'opening_balance' => round(
                $openingBalance,
                2
            ),

            'closing_balance' => round(
                $closingBalance,
                2
            ),

            /*
            |--------------------------------------------------------------
            | FECHAS
            |--------------------------------------------------------------
            */

            'start_date' => $startDate,

            'end_date' => $endDate,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | CALCULAR SALDO SEGÚN NATURALEZA DE LA CUENTA
    |--------------------------------------------------------------------------
    */

    private function calculateBalance(
        string $type,
        float $debit,
        float $credit
    ): float {

        $type = strtoupper($type);

        /*
        | ACTIVO Y GASTO
        */

        if (
            in_array(
                $type,
                [
                    'ACTIVO',
                    'ASSET',
                    'GASTO',
                    'EXPENSE',
                ]
            )
        ) {

            return $debit - $credit;
        }

        /*
        | PASIVO, PATRIMONIO E INGRESO
        */

        return $credit - $debit;
    }
}