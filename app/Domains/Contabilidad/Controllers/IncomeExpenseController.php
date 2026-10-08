<?php

namespace App\Domains\Contabilidad\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

use App\Http\Controllers\Controller;

use App\Domains\Contabilidad\Models\IncomeExpense;
use App\Domains\Contabilidad\Models\AccountingAccount;
use App\Domains\Contabilidad\Models\AccountingPeriod;
use App\Domains\Contabilidad\Models\AccountingEntry;
use App\Domains\Contabilidad\Models\AccountingEntryDetail;

use App\Domains\Caja\Models\CashCategory;

use App\Domains\Caja\Models\CashRegister;
use App\Domains\Caja\Models\CashRegisterSession;

use App\Domains\Empresa\Models\Branch;
use Illuminate\Validation\Rule;
use App\Domains\Caja\Models\CashMovement;

class IncomeExpenseController extends Controller
{

    /**
     * ============================================================
     * LISTAR INGRESOS Y EGRESOS  CASSH-MOVEMENT
     * ============================================================
     */
    public function getIngresoEgresoLista(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.'
            ], 401);
        }

        $validated = $request->validate([
            'date_from' => [
                'nullable',
                'date'
            ],

            'date_to' => [
                'nullable',
                'date',
                'after_or_equal:date_from'
            ],

            'accounting_entry' => [
                'nullable',
                Rule::in([
                    'TODOS',
                    'INGRESO',
                    'EGRESO'
                ])
            ],

            'branch_id' => [
                'nullable',
                'integer'
            ],

            'search' => [
                'nullable',
                'string',
                'max:100'
            ],

            'per_page' => [
                'nullable',
                'integer',
                'min:5',
                'max:100'
            ],

            'page' => [
                'nullable',
                'integer',
                'min:1'
            ],
        ]);

        $perPage = $validated['per_page'] ?? 15;

        /*
    |--------------------------------------------------------------------------
    | CASH MOVEMENTS DEL USUARIO AUTENTICADO
    |--------------------------------------------------------------------------
    */

        $query = CashMovement::query()
            ->with([
                'branch',
                'cashRegister',
                'cashRegisterSession',
                'user',
                'category',
                'sale',
                'saleReturn',
                'salePayment',
            ])
            ->where('id_company', $user->id_company)
            ->where('user_id', $user->id);


        /*
    |--------------------------------------------------------------------------
    | FECHA DESDE
    |--------------------------------------------------------------------------
    */

        if (!empty($validated['date_from'])) {

            $query->whereDate(
                'created_at',
                '>=',
                $validated['date_from']
            );
        }


        /*
    |--------------------------------------------------------------------------
    | FECHA HASTA
    |--------------------------------------------------------------------------
    */

        if (!empty($validated['date_to'])) {

            $query->whereDate(
                'created_at',
                '<=',
                $validated['date_to']
            );
        }


        /*
    |--------------------------------------------------------------------------
    | INGRESO / EGRESO
    |--------------------------------------------------------------------------
    */

        if (
            !empty($validated['accounting_entry']) &&
            $validated['accounting_entry'] !== 'TODOS'
        ) {

            $query->where(
                'type',
                $validated['accounting_entry']
            );
        }


        /*
    |--------------------------------------------------------------------------
    | SUCURSAL
    |--------------------------------------------------------------------------
    */

        if (!empty($validated['branch_id'])) {

            $query->where(
                'branch_id',
                $validated['branch_id']
            );
        }


        /*
    |--------------------------------------------------------------------------
    | BÚSQUEDA
    |--------------------------------------------------------------------------
    */

        if (!empty($validated['search'])) {

            $search = trim($validated['search']);

            $query->where(function ($q) use ($search) {

                $q->where(
                    'description',
                    'like',
                    '%' . $search . '%'
                )

                    ->orWhereHas('category', function ($category) use ($search) {

                        $category->where(
                            'name',
                            'like',
                            '%' . $search . '%'
                        );
                    })

                    ->orWhereHas('user', function ($user) use ($search) {

                        $user->where(
                            'name',
                            'like',
                            '%' . $search . '%'
                        );
                    });
            });
        }


        /*
    |--------------------------------------------------------------------------
    | PAGINACIÓN
    |--------------------------------------------------------------------------
    */

        $movements = $query
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage);


        /*
    |--------------------------------------------------------------------------
    | RESUMEN
    |--------------------------------------------------------------------------
    |
    | IMPORTANTE:
    | También filtramos por el usuario autenticado.
    |
    */

        $summaryQuery = CashMovement::query()
            ->where('id_company', $user->id_company)
            ->where('user_id', $user->id);


        /*
    |--------------------------------------------------------------------------
    | FECHAS
    |--------------------------------------------------------------------------
    */

        if (!empty($validated['date_from'])) {

            $summaryQuery->whereDate(
                'created_at',
                '>=',
                $validated['date_from']
            );
        }

        if (!empty($validated['date_to'])) {

            $summaryQuery->whereDate(
                'created_at',
                '<=',
                $validated['date_to']
            );
        }


        /*
    |--------------------------------------------------------------------------
    | TIPO
    |--------------------------------------------------------------------------
    */

        if (
            !empty($validated['accounting_entry']) &&
            $validated['accounting_entry'] !== 'TODOS'
        ) {

            $summaryQuery->where(
                'type',
                $validated['accounting_entry']
            );
        }


        /*
    |--------------------------------------------------------------------------
    | SUCURSAL
    |--------------------------------------------------------------------------
    */

        if (!empty($validated['branch_id'])) {

            $summaryQuery->where(
                'branch_id',
                $validated['branch_id']
            );
        }


        /*
    |--------------------------------------------------------------------------
    | BÚSQUEDA
    |--------------------------------------------------------------------------
    */

        if (!empty($validated['search'])) {

            $search = trim($validated['search']);

            $summaryQuery->where(function ($q) use ($search) {

                $q->where(
                    'description',
                    'like',
                    '%' . $search . '%'
                )

                    ->orWhereHas('category', function ($category) use ($search) {

                        $category->where(
                            'name',
                            'like',
                            '%' . $search . '%'
                        );
                    })

                    ->orWhereHas('user', function ($user) use ($search) {

                        $user->where(
                            'name',
                            'like',
                            '%' . $search . '%'
                        );
                    });
            });
        }


        /*
    |--------------------------------------------------------------------------
    | TOTALES
    |--------------------------------------------------------------------------
    */

        $totalIngresos = (clone $summaryQuery)
            ->where('type', 'INGRESO')
            ->sum('amount');

        $totalEgresos = (clone $summaryQuery)
            ->where('type', 'EGRESO')
            ->sum('amount');

        $saldo = $totalIngresos - $totalEgresos;


        /*
    |--------------------------------------------------------------------------
    | RESPUESTA
    |--------------------------------------------------------------------------
    */

        return response()->json([

            'success' => true,

            'data' => $movements->items(),

            'pagination' => [

                'current_page' => $movements->currentPage(),

                'per_page' => $movements->perPage(),

                'total' => $movements->total(),

                'last_page' => $movements->lastPage(),

                'from' => $movements->firstItem(),

                'to' => $movements->lastItem(),

            ],

            'resumen' => [

                'total_ingresos' => round(
                    (float) $totalIngresos,
                    2
                ),

                'total_egresos' => round(
                    (float) $totalEgresos,
                    2
                ),

                'saldo' => round(
                    (float) $saldo,
                    2
                ),

            ],

        ]);
    }
    public function store(Request $request)
    {
        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | VALIDACIÓN DEL REQUEST
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([

            'branch_id' => [
                'required',
                'integer',
            ],

            'cash_register_id' => [
                'required',
                'integer',
            ],

            'cash_register_session_id' => [
                'required',
                'integer',
            ],

            'cash_category_id' => [
                'required',
                'integer',
            ],

            'type' => [
                'required',
                'in:INGRESO,EGRESO',
            ],

            'amount' => [
                'required',
                'numeric',
                'min:0.01',
            ],

            'date' => [
                'required',
                'date',
            ],

            'description' => [
                'nullable',
                'string',
                'max:500',
            ],
        ]);

        try {

            return DB::transaction(
                function () use (
                    $request,
                    $user,
                    $validated
                ) {

                    /*
                    |--------------------------------------------------------------------------
                    | DATOS PRINCIPALES
                    |--------------------------------------------------------------------------
                    */

                    $companyId = $user->id_company;

                    $type = strtoupper(
                        trim(
                            $validated['type']
                        )
                    );

                    $amount = round(
                        (float) $validated['amount'],
                        2
                    );

                    $description =
                        !empty($validated['description'])
                        ? trim(
                            $validated['description']
                        )
                        : null;


                    /*
                    |--------------------------------------------------------------------------
                    | SUCURSAL
                    |--------------------------------------------------------------------------
                    */

                    $branch = Branch::query()
                        ->where(
                            'id',
                            $validated['branch_id']
                        )
                        ->where(
                            'id_company',
                            $companyId
                        )
                        ->first();

                    if (!$branch) {

                        throw ValidationException::withMessages([

                            'branch_id' =>
                            'La sucursal no pertenece a la empresa actual.',

                        ]);
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | CAJA
                    |--------------------------------------------------------------------------
                    */

                    $cashRegister = CashRegister::query()
                        ->where(
                            'id',
                            $validated['cash_register_id']
                        )
                        ->where(
                            'id_company',
                            $companyId
                        )
                        ->where(
                            'branch_id',
                            $branch->id
                        )
                        ->first();

                    if (!$cashRegister) {

                        throw ValidationException::withMessages([

                            'cash_register_id' =>
                            'La caja no pertenece a la sucursal seleccionada.',

                        ]);
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | SESIÓN DE CAJA
                    |--------------------------------------------------------------------------
                    */

                    $session = CashRegisterSession::query()
                        ->where(
                            'id',
                            $validated['cash_register_session_id']
                        )
                        ->where(
                            'cash_register_id',
                            $cashRegister->id
                        )
                        ->where(
                            'status',
                            'OPEN'
                        )
                        ->lockForUpdate()
                        ->first();

                    if (!$session) {

                        throw ValidationException::withMessages([

                            'cash_register_session_id' =>
                            'La caja no tiene una sesión abierta válida.',

                        ]);
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | CATEGORÍA DE CAJA
                    |--------------------------------------------------------------------------
                    */

                    $category = CashCategory::query()
                        ->where(
                            'id',
                            $validated['cash_category_id']
                        )
                        ->where(
                            'id_company',
                            $companyId
                        )
                        ->where(
                            'status',
                            true
                        )
                        ->with([
                            'accountingAccount.accountType',
                        ])
                        ->first();

                    if (!$category) {

                        throw ValidationException::withMessages([

                            'cash_category_id' =>
                            'La categoría de caja no existe o está inactiva.',

                        ]);
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | VALIDAR TIPO DE CATEGORÍA
                    |--------------------------------------------------------------------------
                    */

                    if ($category->type !== $type) {

                        throw ValidationException::withMessages([

                            'cash_category_id' =>
                            "La categoría {$category->name} no corresponde al movimiento {$type}.",

                        ]);
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | CUENTA CONTABLE DE LA CATEGORÍA
                    |--------------------------------------------------------------------------
                    */

                    $account = $category->accountingAccount;

                    if (!$account) {

                        throw ValidationException::withMessages([

                            'cash_category_id' =>
                            'La categoría no tiene una cuenta contable configurada en el Plan de Cuentas.',

                        ]);
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | VALIDAR EMPRESA DE LA CUENTA
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $account->id_company !==
                        $companyId
                    ) {

                        throw ValidationException::withMessages([

                            'cash_category_id' =>
                            'La cuenta contable de la categoría no pertenece a la empresa actual.',

                        ]);
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | CUENTA ACTIVA
                    |--------------------------------------------------------------------------
                    */

                    if (!$account->status) {

                        throw ValidationException::withMessages([

                            'cash_category_id' =>
                            'La cuenta contable seleccionada está inactiva.',

                        ]);
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | CUENTA PERMITE MOVIMIENTOS
                    |--------------------------------------------------------------------------
                    */

                    if (!$account->allows_entries) {

                        throw ValidationException::withMessages([

                            'cash_category_id' =>
                            'La cuenta contable seleccionada no permite movimientos.',

                        ]);
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | TIPO CONTABLE
                    |--------------------------------------------------------------------------
                    */

                    $accountType = $account->accountType;

                    if (!$accountType) {

                        throw ValidationException::withMessages([

                            'cash_category_id' =>
                            'La cuenta contable no tiene un tipo contable configurado.',

                        ]);
                    }

                    $accountTypeCode = strtoupper(
                        trim(
                            $accountType->code
                        )
                    );


                    /*
                    |--------------------------------------------------------------------------
                    | INGRESO → ING
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $type === 'INGRESO'
                        &&
                        $accountTypeCode !== 'ING'
                    ) {

                        throw ValidationException::withMessages([

                            'cash_category_id' =>
                            "La categoría de INGRESO debe apuntar a una cuenta de tipo INGRESO. La cuenta {$account->code} pertenece al tipo {$accountType->code}.",

                        ]);
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | EGRESO → GAS / COS
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $type === 'EGRESO'
                        &&
                        !in_array(
                            $accountTypeCode,
                            [
                                'GAS',
                                'COS',
                            ],
                            true
                        )
                    ) {

                        throw ValidationException::withMessages([

                            'cash_category_id' =>
                            "La categoría de EGRESO debe apuntar a una cuenta de GASTO o COSTO. La cuenta {$account->code} pertenece al tipo {$accountType->code}.",

                        ]);
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | PERÍODO CONTABLE
                    |--------------------------------------------------------------------------
                    */

                    $period = AccountingPeriod::query()
                        ->where(
                            'id_company',
                            $companyId
                        )
                        ->where(
                            'status',
                            'OPEN'
                        )
                        ->whereDate(
                            'start_date',
                            '<=',
                            $validated['date']
                        )
                        ->whereDate(
                            'end_date',
                            '>=',
                            $validated['date']
                        )
                        ->first();


                    /*
                    |--------------------------------------------------------------------------
                    | FALLBACK PERÍODO
                    |--------------------------------------------------------------------------
                    */

                    if (!$period) {

                        $period = AccountingPeriod::query()
                            ->where(
                                'id_company',
                                $companyId
                            )
                            ->where(
                                'status',
                                'OPEN'
                            )
                            ->first();
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | VALIDAR PERÍODO
                    |--------------------------------------------------------------------------
                    */

                    if (!$period) {

                        throw ValidationException::withMessages([

                            'date' =>
                            'No existe un período contable abierto para registrar el movimiento.',

                        ]);
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | CUENTA CONTABLE DE CAJA
                    |--------------------------------------------------------------------------
                    */

                    $cashAccount = $this->findCashAccount(
                        $companyId,
                        $cashRegister
                    );

                    if (!$cashAccount) {

                        throw ValidationException::withMessages([

                            'cash_register_id' =>
                            'No existe una cuenta contable de CAJA configurada en el Plan de Cuentas para esta empresa.',

                        ]);
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | VALIDAR CUENTA CAJA
                    |--------------------------------------------------------------------------
                    */

                    if (!$cashAccount->status) {

                        throw ValidationException::withMessages([

                            'cash_register_id' =>
                            "La cuenta {$cashAccount->code} configurada como CAJA está inactiva.",

                        ]);
                    }


                    if (!$cashAccount->allows_entries) {

                        throw ValidationException::withMessages([

                            'cash_register_id' =>
                            "La cuenta {$cashAccount->code} configurada como CAJA no permite movimientos.",

                        ]);
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | TIPO CUENTA CAJA
                    |--------------------------------------------------------------------------
                    */

                    $cashAccountType = $cashAccount->accountType;

                    if (!$cashAccountType) {

                        throw ValidationException::withMessages([

                            'cash_register_id' =>
                            "La cuenta {$cashAccount->code} no tiene tipo contable.",

                        ]);
                    }


                    $cashAccountTypeCode = strtoupper(
                        trim(
                            $cashAccountType->code
                        )
                    );


                    if ($cashAccountTypeCode !== 'ACT') {

                        throw ValidationException::withMessages([

                            'cash_register_id' =>
                            "La cuenta {$cashAccount->code} configurada como CAJA debe pertenecer al tipo ACTIVO.",

                        ]);
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | CÓDIGO INGRESO / EGRESO
                    |--------------------------------------------------------------------------
                    */

                    $code = $this->generateIncomeExpenseCode(
                        $companyId,
                        $type
                    );


                    /*
                    |--------------------------------------------------------------------------
                    | MOVIMIENTO DE CAJA
                    |--------------------------------------------------------------------------
                    */

                    $cashMovement = CashMovement::create([

                        'id_company' =>
                        $companyId,

                        'branch_id' =>
                        $branch->id,

                        'cash_register_id' =>
                        $cashRegister->id,

                        'cash_register_session_id' =>
                        $session->id,

                        'user_id' =>
                        $user->id,

                        'cash_category_id' =>
                        $category->id,

                        'type' =>
                        $type,

                        'amount' =>
                        $amount,

                        'description' =>
                        $description
                            ??
                            $category->name,
                    ]);


                    /*
                    |--------------------------------------------------------------------------
                    | INGRESO / EGRESO
                    |--------------------------------------------------------------------------
                    */

                    $incomeExpense = IncomeExpense::create([

                        'id_company' =>
                        $companyId,

                        'branch_id' =>
                        $branch->id,

                        'cash_register_id' =>
                        $cashRegister->id,

                        'cash_register_session_id' =>
                        $session->id,

                        'user_id' =>
                        $user->id,

                        'cash_category_id' =>
                        $category->id,

                        /*
                        | Cuenta de contrapartida.
                        |
                        | INGRESO → ING
                        | EGRESO  → GAS/COS
                        */

                        'accounting_account_id' =>
                        $account->id,

                        'accounting_period_id' =>
                        $period->id,

                        'cash_movement_id' =>
                        $cashMovement->id,

                        'code' =>
                        $code,

                        'date' =>
                        $validated['date'],

                        'type' =>
                        $type,

                        'amount' =>
                        $amount,

                        'description' =>
                        $description
                            ??
                            $category->name,

                        'status' =>
                        'POSTED',
                    ]);


                    /*
                    |--------------------------------------------------------------------------
                    | CÓDIGO DEL ASIENTO
                    |--------------------------------------------------------------------------
                    */

                    $entryCode = $this->generateEntryCode(
                        $companyId
                    );


                    /*
                    |--------------------------------------------------------------------------
                    | ASIENTO CONTABLE
                    |--------------------------------------------------------------------------
                    */

                    $entry = AccountingEntry::create([

                        'id_company' =>
                        $companyId,

                        'branch_id' =>
                        $branch->id,

                        'accounting_period_id' =>
                        $period->id,

                        'code_entry' =>
                        $entryCode,

                        'date' =>
                        $validated['date'],

                        'type' =>
                        $type,

                        'description' =>
                        $description
                            ??
                            $category->name,

                        'reference_type' =>
                        IncomeExpense::class,

                        'reference_id' =>
                        $incomeExpense->id,

                        'user_id' =>
                        $user->id,

                        'status' =>
                        'POSTED',
                    ]);


                    /*
                    |--------------------------------------------------------------------------
                    | DETALLES DEL ASIENTO
                    |--------------------------------------------------------------------------
                    */

                    if ($type === 'INGRESO') {

                        /*
                        |--------------------------------------------------------------------------
                        | INGRESO
                        |
                        | CAJA       DEBE
                        | INGRESO    HABER
                        |--------------------------------------------------------------------------
                        */

                        AccountingEntryDetail::create([

                            'accounting_entry_id' =>
                            $entry->id,

                            'accounting_account_id' =>
                            $cashAccount->id,

                            'description' =>
                            $category->name,

                            'debit' =>
                            $amount,

                            'credit' =>
                            0,
                        ]);


                        AccountingEntryDetail::create([

                            'accounting_entry_id' =>
                            $entry->id,

                            'accounting_account_id' =>
                            $account->id,

                            'description' =>
                            $category->name,

                            'debit' =>
                            0,

                            'credit' =>
                            $amount,
                        ]);
                    } else {

                        /*
                        |--------------------------------------------------------------------------
                        | EGRESO
                        |
                        | GASTO/COSTO    DEBE
                        | CAJA           HABER
                        |--------------------------------------------------------------------------
                        */

                        AccountingEntryDetail::create([

                            'accounting_entry_id' =>
                            $entry->id,

                            'accounting_account_id' =>
                            $account->id,

                            'description' =>
                            $category->name,

                            'debit' =>
                            $amount,

                            'credit' =>
                            0,
                        ]);


                        AccountingEntryDetail::create([

                            'accounting_entry_id' =>
                            $entry->id,

                            'accounting_account_id' =>
                            $cashAccount->id,

                            'description' =>
                            $category->name,

                            'debit' =>
                            0,

                            'credit' =>
                            $amount,
                        ]);
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | VINCULAR ASIENTO
                    |--------------------------------------------------------------------------
                    */

                    $incomeExpense->update([

                        'accounting_entry_id' =>
                        $entry->id,

                    ]);


                    /*
                    |--------------------------------------------------------------------------
                    | RECALCULAR CAJA
                    |--------------------------------------------------------------------------
                    */

                    $this->recalculateSession(
                        $session
                    );


                    /*
                    |--------------------------------------------------------------------------
                    | RECARGAR RELACIONES
                    |--------------------------------------------------------------------------
                    */

                    $incomeExpense->load([

                        'branch',

                        'cashRegister',

                        'cashRegisterSession',

                        'cashCategory.accountingAccount.accountType',

                        'accountingAccount.accountType',

                        'accountingPeriod',

                        'cashMovement',

                        'accountingEntry.details.account',

                        'user',
                    ]);


                    /*
                    |--------------------------------------------------------------------------
                    | RESPUESTA
                    |--------------------------------------------------------------------------
                    */

                    return response()->json([

                        'success' =>
                        true,

                        'message' =>
                        'Movimiento registrado correctamente.',

                        'data' => [

                            'income_expense' =>
                            $incomeExpense,

                            'cash_movement' =>
                            $cashMovement,

                            'accounting_entry' =>
                            $entry->load(
                                'details.account'
                            ),

                            'cash_account' =>
                            $cashAccount,

                            'counterpart_account' =>
                            $account,

                            'session' =>
                            $session->fresh(),
                        ],

                    ], 201);
                }
            );
        } catch (ValidationException $e) {

            throw $e;
        } catch (\Throwable $e) {

            Log::error(
                'Error registrando ingreso/egreso',
                [

                    'message' =>
                    $e->getMessage(),

                    'file' =>
                    $e->getFile(),

                    'line' =>
                    $e->getLine(),

                    'user_id' =>
                    $user->id,

                    'company_id' =>
                    $user->id_company,

                    'payload' =>
                    $request->all(),
                ]
            );

            return response()->json([

                'success' =>
                false,

                'message' =>
                'Error al registrar el movimiento.',

                'error' =>
                $e->getMessage(),

            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $user = $request->user();

        $query = IncomeExpense::query()
            ->where(
                'id_company',
                $user->id_company
            )
            ->with([
                'branch:id,branch_name',

                'cashRegister:id,code,name',

                'cashRegisterSession:id,cash_register_id,status,opening_amount,expected_amount',

                'cashCategory:id,code,name,type,accounting_account_id',

                'accountingAccount:id,account_type_id,parent_id,code,name,level,allows_entries,status',

                'accountingAccount.accountType:id,code,name,nature',

                'accountingPeriod:id,period,status',

                'accountingEntry:id,code_entry,date,type,description,status',

                'user:id,name',
            ])
            ->orderByDesc('id');

        /*
        |--------------------------------------------------------------------------
        | FILTRO TIPO
        |--------------------------------------------------------------------------
        */

        if ($request->filled('type')) {
            $query->where(
                'type',
                strtoupper(
                    trim(
                        $request->type
                    )
                )
            );
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
        | FILTRO CATEGORÍA
        |--------------------------------------------------------------------------
        */

        if ($request->filled('cash_category_id')) {
            $query->where(
                'cash_category_id',
                $request->cash_category_id
            );
        }

        /*
        |--------------------------------------------------------------------------
        | FILTRO FECHA DESDE
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
        | FILTRO FECHA HASTA
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
        | PAGINACIÓN
        |--------------------------------------------------------------------------
        */

        $perPage = min(
            max(
                (int) $request->get(
                    'per_page',
                    20
                ),
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
    | SHOW
    |--------------------------------------------------------------------------
    */

    public function show(
        Request $request,
        $id
    ) {
        $user = $request->user();

        $movement = IncomeExpense::query()
            ->where(
                'id_company',
                $user->id_company
            )
            ->with([
                'branch',

                'cashRegister',

                'cashRegisterSession',

                'cashCategory.accountingAccount.accountType',

                'accountingAccount.accountType',

                'accountingPeriod',

                'cashMovement',

                'accountingEntry.details.account',

                'user',
            ])
            ->findOrFail($id);

        return response()->json([
            'success' => true,

            'data' => $movement,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */


    /*
    |--------------------------------------------------------------------------
    | GENERAR CÓDIGO INGRESO / EGRESO
    |--------------------------------------------------------------------------
    */

    private function generateIncomeExpenseCode(
        string $companyId,
        string $type
    ): string {

        $prefix =
            $type === 'INGRESO'
            ? 'ING'
            : 'EGR';


        $last = IncomeExpense::query()
            ->where(
                'id_company',
                $companyId
            )
            ->where(
                'code',
                'like',
                $prefix . '-%'
            )
            ->lockForUpdate()
            ->orderByDesc('id')
            ->first();


        $number = 1;


        if ($last) {

            $lastCode =
                strtoupper(
                    trim(
                        $last->code
                    )
                );


            if (
                preg_match(
                    '/^[A-Z]+-(\d+)$/',
                    $lastCode,
                    $matches
                )
            ) {

                $number =
                    ((int) $matches[1])
                    + 1;
            }
        }


        return sprintf(
            '%s-%06d',
            $prefix,
            $number
        );
    }


    /*
    |--------------------------------------------------------------------------
    | GENERAR CÓDIGO DEL ASIENTO
    |--------------------------------------------------------------------------
    */

    private function generateEntryCode(
        string $companyId
    ): string {

        $lastEntry = AccountingEntry::query()
            ->where(
                'id_company',
                $companyId
            )
            ->where(
                'code_entry',
                'like',
                'ASI-%'
            )
            ->lockForUpdate()
            ->orderByDesc('id')
            ->first();


        $number = 1;


        if ($lastEntry) {

            $lastCode =
                strtoupper(
                    trim(
                        $lastEntry->code_entry
                    )
                );


            if (
                preg_match(
                    '/^ASI-(\d+)$/',
                    $lastCode,
                    $matches
                )
            ) {

                $number =
                    ((int) $matches[1])
                    + 1;
            }
        }


        return sprintf(
            'ASI-%06d',
            $number
        );
    }


    /*
    |--------------------------------------------------------------------------
    | BUSCAR CUENTA CONTABLE DE CAJA
    |--------------------------------------------------------------------------
    */

    private function findCashAccount(
        string $companyId,
        CashRegister $cashRegister
    ): ?AccountingAccount {

        /*
        |--------------------------------------------------------------------------
        | 1. SI LA CAJA TIENE accounting_account_id
        |--------------------------------------------------------------------------
        */

        if (
            isset(
                $cashRegister->accounting_account_id
            )
            &&
            $cashRegister->accounting_account_id
        ) {

            $account = AccountingAccount::query()

                ->where(
                    'id',
                    $cashRegister->accounting_account_id
                )

                ->where(
                    'id_company',
                    $companyId
                )

                ->where(
                    'status',
                    true
                )

                ->where(
                    'allows_entries',
                    true
                )

                ->with(
                    'accountType'
                )

                ->first();


            if ($account) {

                return $account;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | 2. BUSCAR CUENTA POR NOMBRE CAJA
        |--------------------------------------------------------------------------
        */

        $account = AccountingAccount::query()

            ->where(
                'id_company',
                $companyId
            )

            ->where(
                'status',
                true
            )

            ->where(
                'allows_entries',
                true
            )

            ->whereHas(
                'accountType',
                function ($query) {

                    $query->where(
                        'code',
                        'ACT'
                    );
                }
            )

            ->where(
                function ($query) use (
                    $cashRegister
                ) {

                    $query

                        ->whereRaw(
                            'UPPER(name) LIKE ?',
                            [
                                '%CAJA%'
                            ]
                        )

                        ->orWhere(
                            'name',
                            'like',
                            '%' .
                                $cashRegister->name .
                                '%'
                        )

                        ->orWhere(
                            'name',
                            'like',
                            '%' .
                                $cashRegister->code .
                                '%'
                        );
                }
            )

            ->with(
                'accountType'
            )

            ->orderBy(
                'code'
            )

            ->first();


        if ($account) {

            return $account;
        }


        /*
        |--------------------------------------------------------------------------
        | 3. FALLBACK:
        | CUALQUIER ACTIVO QUE PERMITA MOVIMIENTOS
        |--------------------------------------------------------------------------
        */

        return AccountingAccount::query()

            ->where(
                'id_company',
                $companyId
            )

            ->where(
                'status',
                true
            )

            ->where(
                'allows_entries',
                true
            )

            ->whereHas(
                'accountType',
                function ($query) {

                    $query->where(
                        'code',
                        'ACT'
                    );
                }
            )

            ->with(
                'accountType'
            )

            ->orderBy(
                'code'
            )

            ->first();
    }


    /*
    |--------------------------------------------------------------------------
    | RECALCULAR SESIÓN DE CAJA
    |--------------------------------------------------------------------------
    */

    private function recalculateSession(
        CashRegisterSession $session
    ): void {

        /*
        |--------------------------------------------------------------------------
        | INGRESOS
        |--------------------------------------------------------------------------
        */

        $income = CashMovement::query()

            ->where(
                'cash_register_session_id',
                $session->id
            )

            ->where(
                'type',
                'INGRESO'
            )

            ->sum(
                'amount'
            );


        /*
        |--------------------------------------------------------------------------
        | EGRESOS
        |--------------------------------------------------------------------------
        */

        $expense = CashMovement::query()

            ->where(
                'cash_register_session_id',
                $session->id
            )

            ->where(
                'type',
                'EGRESO'
            )

            ->sum(
                'amount'
            );


        /*
        |--------------------------------------------------------------------------
        | VENTAS
        |--------------------------------------------------------------------------
        |
        | NOTA:
        | Tu migration actual de cash_movements solamente permite:
        |
        | INGRESO
        | EGRESO
        |
        | Por eso NO se consulta VENTA ni DEVOLUCION aquí.
        |
        | Si las ventas están registradas como CashMovement, su diseño
        | deberá integrarse posteriormente con esta estructura.
        |
        */


        /*
        |--------------------------------------------------------------------------
        | ESPERADO
        |--------------------------------------------------------------------------
        */

        $expected =

            (float) $session->opening_amount

            +

            (float) $income

            -

            (float) $expense;


        /*
        |--------------------------------------------------------------------------
        | ACTUALIZAR SESIÓN
        |--------------------------------------------------------------------------
        */

        $session->update([

            'expected_amount' =>
            round(
                $expected,
                2
            ),

        ]);
    }
}
