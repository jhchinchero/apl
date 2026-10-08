<?php

namespace App\Domains\Contabilidad\Services;

use App\Domains\Contabilidad\Models\AccountingEntry;
use Illuminate\Support\Facades\Auth;

class AccountingJournalService
{
    public function getJournal(array $filters = [])
    {
        $user = Auth::user();

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
        | PAGINACIÓN
        |--------------------------------------------------------------------------
        */

        $page = max(
            1,
            (int) ($filters['page'] ?? 1)
        );

        $perPage = (int) ($filters['per_page'] ?? 20);

        if ($perPage <= 0) {
            $perPage = 20;
        }

        if ($perPage > 100) {
            $perPage = 100;
        }

        /*
        |--------------------------------------------------------------------------
        | CONSULTA DE ASIENTOS
        |--------------------------------------------------------------------------
        */

        $query = AccountingEntry::query()

            ->where(
                'id_company',
                $user->id_company
            )

            /*
            | Solo asientos contabilizados
            */

            ->where(
                'status',
                'POSTED'
            )

            /*
            | Relaciones
            */

            ->with([
                'branch',
                'user',
                'accountingPeriod',
                'details.account',
            ]);

        /*
        |--------------------------------------------------------------------------
        | FECHA DESDE
        |--------------------------------------------------------------------------
        */

        if (!empty($startDate)) {

            $query->whereDate(
                'date',
                '>=',
                $startDate
            );
        }

        /*
        |--------------------------------------------------------------------------
        | FECHA HASTA
        |--------------------------------------------------------------------------
        */

        if (!empty($endDate)) {

            $query->whereDate(
                'date',
                '<=',
                $endDate
            );
        }

        /*
        |--------------------------------------------------------------------------
        | SUCURSAL
        |--------------------------------------------------------------------------
        */

        if (!empty($filters['branch_id'])) {

            $query->where(
                'branch_id',
                $filters['branch_id']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | TIPO DE ASIENTO
        |--------------------------------------------------------------------------
        */

        if (!empty($filters['type'])) {

            $query->where(
                'type',
                $filters['type']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | BUSCAR
        |--------------------------------------------------------------------------
        */

        if (!empty($filters['search'])) {

            $search = trim(
                $filters['search']
            );

            $query->where(function ($q) use ($search) {

                /*
                | Buscar en asiento
                */

                $q->where(
                    'code_entry',
                    'LIKE',
                    "%{$search}%"
                )

                /*
                | Buscar en descripción
                */

                ->orWhere(
                    'description',
                    'LIKE',
                    "%{$search}%"
                )

                /*
                | Buscar también por cuenta
                */

                ->orWhereHas(
                    'details.account',
                    function ($accountQuery) use ($search) {

                        $accountQuery
                            ->where(
                                'code',
                                'LIKE',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'name',
                                'LIKE',
                                "%{$search}%"
                            );
                    }
                );
            });
        }

        /*
        |--------------------------------------------------------------------------
        | OBTENER TODOS LOS ASIENTOS
        |--------------------------------------------------------------------------
        |
        | Primero obtenemos los asientos.
        | Después construimos las filas.
        |
        */

        $entries = $query

            ->orderBy(
                'date',
                'asc'
            )

            ->orderBy(
                'id',
                'asc'
            )

            ->get();

        /*
        |--------------------------------------------------------------------------
        | CONSTRUIR FILAS
        |--------------------------------------------------------------------------
        */

        $rows = [];

        $totalDebit = 0;

        $totalCredit = 0;

        /*
        |--------------------------------------------------------------------------
        | RECORRER ASIENTOS
        |--------------------------------------------------------------------------
        */

        foreach ($entries as $entry) {

            /*
            |--------------------------------------------------------------
            | DETALLES DEL ASIENTO
            |--------------------------------------------------------------
            */

            $details = [];

            $entryDebit = 0;

            $entryCredit = 0;

            foreach ($entry->details as $detail) {

                $debit = (float) (
                    $detail->debit ?? 0
                );

                $credit = (float) (
                    $detail->credit ?? 0
                );

                $entryDebit += $debit;

                $entryCredit += $credit;

                $totalDebit += $debit;

                $totalCredit += $credit;

                /*
                | Detalle para la expansión
                */

                $details[] = [

                    'id' => $detail->id,

                    'account_id' =>
                        $detail->accounting_account_id,

                    'account_code' =>
                        $detail->account
                            ? $detail->account->code
                            : null,

                    'account_name' =>
                        $detail->account
                            ? $detail->account->name
                            : null,

                    'description' =>
                        $detail->description,

                    'debit' => $debit,

                    'credit' => $credit,
                ];
            }

            /*
            |--------------------------------------------------------------
            | CREAR UNA FILA PRINCIPAL
            |--------------------------------------------------------------
            |
            | Tomamos el primer detalle para mostrar
            | cuenta/código en la tabla principal.
            |
            */

            $firstDetail = $entry->details->first();

            $rows[] = [

                /*
                | ID ÚNICO DEL ASIENTO
                */

                'id' => $entry->id,

                /*
                | FECHA
                */

                'date' => $entry->date
                    ? $entry->date->format(
                        'Y-m-d H:i:s'
                    )
                    : null,

                /*
                | ASIENTO
                */

                'code_entry' =>
                    $entry->code_entry,

                'entry_id' =>
                    $entry->id,

                /*
                | TIPO
                */

                'type' =>
                    $entry->type,

                /*
                | DESCRIPCIÓN
                */

                'entry_description' =>
                    $entry->description,

                /*
                | CUENTA PRINCIPAL
                */

                'account_id' =>
                    $firstDetail
                        ? $firstDetail->accounting_account_id
                        : null,

                'account_code' =>
                    $firstDetail && $firstDetail->account
                        ? $firstDetail->account->code
                        : null,

                'account_name' =>
                    $firstDetail && $firstDetail->account
                        ? $firstDetail->account->name
                        : null,

                /*
                | MOVIMIENTOS DEL ASIENTO
                */

                'debit' =>
                    round($entryDebit, 2),

                'credit' =>
                    round($entryCredit, 2),

                /*
                | DETALLES PARA EXPANSIÓN
                */

                'details' =>
                    $details,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | TOTAL DE ASIENTOS
        |--------------------------------------------------------------------------
        */

        $total = count($rows);

        /*
        |--------------------------------------------------------------------------
        | PAGINACIÓN MANUAL
        |--------------------------------------------------------------------------
        |
        | Como ya obtuvimos los datos, hacemos la paginación
        | en memoria.
        |
        */

        $lastPage = max(
            1,
            (int) ceil(
                $total / $perPage
            )
        );

        /*
        | Si la página solicitada supera la última,
        | usamos la última página.
        */

        if ($page > $lastPage) {
            $page = $lastPage;
        }

        $offset = (
            $page - 1
        ) * $perPage;

        $items = array_slice(
            $rows,
            $offset,
            $perPage
        );

        /*
        |--------------------------------------------------------------------------
        | RETORNO
        |--------------------------------------------------------------------------
        */

        return [

            /*
            |--------------------------------------------------------------
            | ITEMS
            |--------------------------------------------------------------
            */

            'items' => array_values(
                $items
            ),

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

                'difference' => round(
                    $totalDebit - $totalCredit,
                    2
                ),
            ],

            /*
            |--------------------------------------------------------------
            | PAGINACIÓN
            |--------------------------------------------------------------
            */

            'pagination' => [

                'current_page' =>
                    $page,

                'per_page' =>
                    $perPage,

                'total' =>
                    $total,

                'last_page' =>
                    $lastPage,
            ],

            /*
            |--------------------------------------------------------------
            | FILTROS APLICADOS
            |--------------------------------------------------------------
            */

            'start_date' =>
                $startDate,

            'end_date' =>
                $endDate,
        ];
    }
}