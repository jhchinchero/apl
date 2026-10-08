<?php

namespace App\Domains\Contabilidad\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use App\Domains\Contabilidad\Models\AccountingEntry;
use App\Domains\Contabilidad\Models\AccountingEntryDetail;
use App\Domains\Contabilidad\Models\AccountingAccount;

class AccountingEntryController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | LISTAR ASIENTOS
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        try {

            $user = Auth::user();

            $idCompany = $user->id_company;

            $query = AccountingEntry::query()
                ->where('id_company', $idCompany)
                ->with([
                    'details.account',
                    'branch',
                    'accountingPeriod',
                    'user'
                ])
                ->orderByDesc('date')
                ->orderByDesc('id');

            /*
            |--------------------------------------------------------------------------
            | BUSCAR
            |--------------------------------------------------------------------------
            */

            if ($request->filled('search')) {

                $search = trim($request->search);

                $query->where(function ($q) use ($search) {

                    $q->where('code_entry', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('reference_id', 'like', "%{$search}%");
                });
            }

            /*
            |--------------------------------------------------------------------------
            | FECHA DESDE
            |--------------------------------------------------------------------------
            */

            if ($request->filled('date_from')) {

                $query->whereDate(
                    'date',
                    '>=',
                    $request->date_from
                );
            }

            /*
            |--------------------------------------------------------------------------
            | FECHA HASTA
            |--------------------------------------------------------------------------
            */

            if ($request->filled('date_to')) {

                $query->whereDate(
                    'date',
                    '<=',
                    $request->date_to
                );
            }

            /*
            |--------------------------------------------------------------------------
            | ESTADO
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
            | PAGINACIÓN
            |--------------------------------------------------------------------------
            */

            $perPage = min(
                max((int) $request->get('per_page', 15), 1),
                100
            );

            $entries = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Asientos contables obtenidos correctamente.',
                'data' => $entries->items(),
                'meta' => [
                    'current_page' => $entries->currentPage(),
                    'last_page' => $entries->lastPage(),
                    'per_page' => $entries->perPage(),
                    'total' => $entries->total(),
                ]
            ]);
        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los asientos contables.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | MOSTRAR ASIENTO
    |--------------------------------------------------------------------------
    */

    public function show($id)
    {
        try {

            $user = Auth::user();

            $entry = AccountingEntry::query()
                ->where('id_company', $user->id_company)
                ->with([
                    'details.account',
                    'branch',
                    'accountingPeriod',
                    'user'
                ])
                ->find($id);

            if (!$entry) {

                return response()->json([
                    'success' => false,
                    'message' => 'Asiento contable no encontrado.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Asiento obtenido correctamente.',
                'data' => $entry
            ]);
        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el asiento contable.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | LIBRO DIARIO
    |--------------------------------------------------------------------------
    */

    public function journal(Request $request)
    {
        try {

            $user = Auth::user();

            $query = AccountingEntryDetail::query()
                ->whereHas('entry', function ($q) use ($user, $request) {

                    $q->where(
                        'id_company',
                        $user->id_company
                    );

                    $q->where(
                        'status',
                        'POSTED'
                    );

                    if ($request->filled('date_from')) {

                        $q->whereDate(
                            'date',
                            '>=',
                            $request->date_from
                        );
                    }

                    if ($request->filled('date_to')) {

                        $q->whereDate(
                            'date',
                            '<=',
                            $request->date_to
                        );
                    }
                })
                ->with([
                    'entry',
                    'account'
                ])
                ->join(
                    'accounting_entries',
                    'accounting_entry_details.accounting_entry_id',
                    '=',
                    'accounting_entries.id'
                )
                ->orderBy('accounting_entries.date')
                ->orderBy('accounting_entries.id')
                ->select(
                    'accounting_entry_details.*'
                );

            $details = $query->get();

            $data = $details->map(function ($detail) {

                return [
                    'id' => $detail->id,

                    'date' => $detail->entry->date,

                    'code_entry' => $detail->entry->code_entry,

                    'description' => $detail->entry->description,

                    'account_code' => $detail->account?->code,

                    'account_name' => $detail->account?->name,

                    'detail_description' => $detail->description,

                    'debit' => (float) $detail->debit,

                    'credit' => (float) $detail->credit,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el libro diario.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | LIBRO MAYOR
    |--------------------------------------------------------------------------
    */

    public function ledger(Request $request)
    {
        try {

            $user = Auth::user();

            $request->validate([
                'account_id' => [
                    'required',
                    'integer'
                ]
            ]);

            $account = AccountingAccount::query()
                ->where('id_company', $user->id_company)
                ->find($request->account_id);

            if (!$account) {

                return response()->json([
                    'success' => false,
                    'message' => 'Cuenta contable no encontrada.'
                ], 404);
            }

            $query = AccountingEntryDetail::query()
                ->where('accounting_account_id', $account->id)
                ->whereHas('entry', function ($q) use ($user, $request) {

                    $q->where(
                        'id_company',
                        $user->id_company
                    );

                    $q->where(
                        'status',
                        'POSTED'
                    );

                    if ($request->filled('date_from')) {

                        $q->whereDate(
                            'date',
                            '>=',
                            $request->date_from
                        );
                    }

                    if ($request->filled('date_to')) {

                        $q->whereDate(
                            'date',
                            '<=',
                            $request->date_to
                        );
                    }
                })
                ->with([
                    'entry',
                    'account'
                ])
                ->get();

            $balance = 0;

            $data = $query->sortBy(function ($detail) {

                return [
                    $detail->entry->date,
                    $detail->entry->id
                ];
            })->values()->map(function ($detail) use (&$balance) {

                $debit = (float) $detail->debit;

                $credit = (float) $detail->credit;

                $balance += $debit - $credit;

                return [
                    'id' => $detail->id,

                    'date' => $detail->entry->date,

                    'code_entry' => $detail->entry->code_entry,

                    'description' => $detail->entry->description,

                    'debit' => $debit,

                    'credit' => $credit,

                    'balance' => $balance
                ];
            });

            return response()->json([
                'success' => true,

                'account' => [
                    'id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->name,
                    'type' => $account->type
                ],

                'data' => $data,

                'totals' => [
                    'debit' => $data->sum('debit'),
                    'credit' => $data->sum('credit'),
                    'balance' => $balance
                ]
            ]);
        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el libro mayor.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | BALANCE DE COMPROBACIÓN
    |--------------------------------------------------------------------------
    */

    public function trialBalance(Request $request)
    {
        try {

            $user = Auth::user();

            $accounts = AccountingAccount::query()
                ->where(
                    'id_company',
                    $user->id_company
                )
                ->where(
                    'status',
                    true
                )
                ->with([
                    'entryDetails' => function ($q) use ($user, $request) {

                        $q->whereHas('entry', function ($entry) use ($user, $request) {

                            $entry->where(
                                'id_company',
                                $user->id_company
                            );

                            $entry->where(
                                'status',
                                'POSTED'
                            );

                            if ($request->filled('date_from')) {

                                $entry->whereDate(
                                    'date',
                                    '>=',
                                    $request->date_from
                                );
                            }

                            if ($request->filled('date_to')) {

                                $entry->whereDate(
                                    'date',
                                    '<=',
                                    $request->date_to
                                );
                            }
                        });
                    }
                ])
                ->orderBy('code')
                ->get();

            $data = $accounts->map(function ($account) {

                $debit = $account->entryDetails->sum(
                    fn($detail) => (float) $detail->debit
                );

                $credit = $account->entryDetails->sum(
                    fn($detail) => (float) $detail->credit
                );

                return [
                    'id' => $account->id,

                    'code' => $account->code,

                    'name' => $account->name,

                    'type' => $account->type,

                    'debit' => $debit,

                    'credit' => $credit,

                    'balance' => $debit - $credit
                ];
            })->filter(function ($account) {

                return $account['debit'] != 0
                    || $account['credit'] != 0;
            })->values();

            return response()->json([
                'success' => true,

                'data' => $data,

                'totals' => [
                    'debit' => $data->sum('debit'),
                    'credit' => $data->sum('credit'),
                    'balance' => $data->sum('balance')
                ]
            ]);
        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el balance de comprobación.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | ESTADO DE RESULTADOS
    |--------------------------------------------------------------------------
    */

    public function incomeStatement(Request $request)
    {
        try {

            $user = Auth::user();

            $details = AccountingEntryDetail::query()
                ->whereHas('entry', function ($q) use ($user, $request) {

                    $q->where(
                        'id_company',
                        $user->id_company
                    );

                    $q->where(
                        'status',
                        'POSTED'
                    );

                    if ($request->filled('date_from')) {

                        $q->whereDate(
                            'date',
                            '>=',
                            $request->date_from
                        );
                    }

                    if ($request->filled('date_to')) {

                        $q->whereDate(
                            'date',
                            '<=',
                            $request->date_to
                        );
                    }
                })
                ->with([
                    'entry',
                    'account'
                ])
                ->get();

            $grouped = $details
                ->groupBy(function ($detail) {

                    return $detail->account?->type;
                });

            $income = [];
            $expenses = [];
            $costs = [];

            foreach ($grouped as $type => $items) {

                $rows = $items->groupBy(
                    'accounting_account_id'
                )->map(function ($accountItems) {

                    $account = $accountItems->first()->account;

                    return [
                        'id' => $account->id,

                        'code' => $account->code,

                        'name' => $account->name,

                        'debit' => $accountItems->sum(
                            fn($item) => (float) $item->debit
                        ),

                        'credit' => $accountItems->sum(
                            fn($item) => (float) $item->credit
                        )
                    ];
                })->values();

                if (in_array(
                    strtoupper((string) $type),
                    ['INGRESO', 'INCOME', 'REVENUE']
                )) {

                    $income = array_merge(
                        $income,
                        $rows->toArray()
                    );
                } elseif (in_array(
                    strtoupper((string) $type),
                    ['GASTO', 'EXPENSE']
                )) {

                    $expenses = array_merge(
                        $expenses,
                        $rows->toArray()
                    );
                } elseif (in_array(
                    strtoupper((string) $type),
                    ['COSTO', 'COST', 'COGS']
                )) {

                    $costs = array_merge(
                        $costs,
                        $rows->toArray()
                    );
                }
            }

            $totalIncome = collect($income)->sum(
                fn($row) => $row['credit'] - $row['debit']
            );

            $totalExpenses = collect($expenses)->sum(
                fn($row) => $row['debit'] - $row['credit']
            );

            $totalCosts = collect($costs)->sum(
                fn($row) => $row['debit'] - $row['credit']
            );

            $grossProfit = $totalIncome - $totalCosts;

            $netProfit = $grossProfit - $totalExpenses;

            return response()->json([
                'success' => true,

                'data' => [
                    'income' => $income,

                    'costs' => $costs,

                    'expenses' => $expenses,

                    'totals' => [
                        'income' => $totalIncome,

                        'costs' => $totalCosts,

                        'gross_profit' => $grossProfit,

                        'expenses' => $totalExpenses,

                        'net_profit' => $netProfit
                    ]
                ]
            ]);
        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Error al generar el estado de resultados.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | BALANCE GENERAL
    |--------------------------------------------------------------------------
    */

    public function balanceSheet(Request $request)
    {
        try {

            $user = Auth::user();

            $details = AccountingEntryDetail::query()
                ->whereHas('entry', function ($q) use ($user, $request) {

                    $q->where(
                        'id_company',
                        $user->id_company
                    );

                    $q->where(
                        'status',
                        'POSTED'
                    );

                    if ($request->filled('date_to')) {

                        $q->whereDate(
                            'date',
                            '<=',
                            $request->date_to
                        );
                    }
                })
                ->with('account')
                ->get();

            $accounts = $details
                ->groupBy('accounting_account_id')
                ->map(function ($items) {

                    $account = $items->first()->account;

                    $debit = $items->sum(
                        fn($item) => (float) $item->debit
                    );

                    $credit = $items->sum(
                        fn($item) => (float) $item->credit
                    );

                    return [
                        'id' => $account->id,

                        'code' => $account->code,

                        'name' => $account->name,

                        'type' => $account->type,

                        'debit' => $debit,

                        'credit' => $credit,

                        'balance' => $debit - $credit
                    ];
                })
                ->values();

            $assets = $accounts
                ->filter(fn($a) => in_array(
                    strtoupper($a['type']),
                    ['ACTIVO', 'ASSET']
                ))
                ->values();

            $liabilities = $accounts
                ->filter(fn($a) => in_array(
                    strtoupper($a['type']),
                    ['PASIVO', 'LIABILITY']
                ))
                ->values();

            $equity = $accounts
                ->filter(fn($a) => in_array(
                    strtoupper($a['type']),
                    ['PATRIMONIO', 'EQUITY']
                ))
                ->values();

            $income = $accounts
                ->filter(fn($a) => in_array(
                    strtoupper($a['type']),
                    ['INGRESO', 'INCOME', 'REVENUE']
                ))
                ->values();

            $expenses = $accounts
                ->filter(fn($a) => in_array(
                    strtoupper($a['type']),
                    ['GASTO', 'EXPENSE', 'COSTO', 'COST']
                ))
                ->values();

            $netIncome =
                $income->sum(fn($a) => -$a['balance'])
                -
                $expenses->sum(fn($a) => $a['balance']);

            return response()->json([
                'success' => true,

                'data' => [
                    'assets' => $assets,

                    'liabilities' => $liabilities,

                    'equity' => $equity,

                    'net_income' => $netIncome,

                    'totals' => [
                        'assets' => $assets->sum('balance'),

                        'liabilities' => $liabilities->sum('balance'),

                        'equity' => $equity->sum('balance'),

                        'liabilities_equity' =>
                        $liabilities->sum('balance')
                            +
                            $equity->sum('balance')
                            +
                            $netIncome
                    ]
                ]
            ]);
        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Error al generar el balance general.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
