<?php

namespace App\Domains\Caja\Controllers;




use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

use App\Domains\Caja\Models\CashCategory;
use App\Domains\Contabilidad\Models\AccountingAccount;

class CashCategoryController extends Controller
{
    /**
     * ============================================================
     * LISTAR CATEGORÍAS
     * ============================================================
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = CashCategory::query()
            ->where('id_company', $user->id_company)
            ->with([
                'accountingAccount.accountType',
                'accountingAccount.parent',
            ])
            ->orderBy('type')
            ->orderBy('code');

        /*
        |--------------------------------------------------------------------------
        | BUSCAR
        |--------------------------------------------------------------------------
        */
        if ($request->filled('search')) {

            $search = trim($request->search);

            $query->where(function ($q) use ($search) {

                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        /*
        |--------------------------------------------------------------------------
        | FILTRO TIPO
        |--------------------------------------------------------------------------
        */
        if ($request->filled('type')) {

            $query->where(
                'type',
                strtoupper(trim($request->type))
            );
        }

        /*
        |--------------------------------------------------------------------------
        | FILTRO ESTADO
        |--------------------------------------------------------------------------
        */
        if ($request->has('status') && $request->status !== '') {

            $query->where(
                'status',
                filter_var(
                    $request->status,
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE
                )
            );
        }

        /*
        |--------------------------------------------------------------------------
        | PAGINACIÓN
        |--------------------------------------------------------------------------
        */
        $perPage = (int) $request->get('per_page', 10);

        $perPage = min(max($perPage, 1), 100);

        $categories = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Categorías de caja obtenidas correctamente.',
            'data' => $categories,
        ]);
    }


    /**
     * ============================================================
     * MOSTRAR UNA CATEGORÍA
     * ============================================================
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();

        $category = CashCategory::query()
            ->where('id_company', $user->id_company)
            ->with([
                'accountingAccount.accountType',
                'accountingAccount.parent',
            ])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $category,
        ]);
    }


    /**
     * ============================================================
     * CREAR CATEGORÍA
     * ============================================================
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'type' => [
                'required',
                'string',
                Rule::in([
                    'INGRESO',
                    'EGRESO',
                ]),
            ],

            'accounting_account_id' => [
                'required',
                'integer',
            ],

            'status' => [
                'nullable',
                'boolean',
            ],
        ]);


        return DB::transaction(function () use (
            $user,
            $validated
        ) {

            /*
            |--------------------------------------------------------------------------
            | OBTENER CUENTA CONTABLE
            |--------------------------------------------------------------------------
            */

            $account = AccountingAccount::query()
                ->where('id_company', $user->id_company)
                ->where('id', $validated['accounting_account_id'])
                ->with('accountType')
                ->first();

            if (!$account) {

                return response()->json([
                    'success' => false,
                    'message' => 'La cuenta contable seleccionada no pertenece a la empresa.',
                    'errors' => [
                        'accounting_account_id' => [
                            'La cuenta contable seleccionada no es válida.'
                        ]
                    ],
                ], 422);
            }


            /*
            |--------------------------------------------------------------------------
            | LA CUENTA DEBE PERMITIR MOVIMIENTOS
            |--------------------------------------------------------------------------
            */

            if (!$account->allows_entries) {

                return response()->json([
                    'success' => false,
                    'message' => 'La cuenta seleccionada no permite registros contables.',
                    'errors' => [
                        'accounting_account_id' => [
                            'Debe seleccionar una cuenta de movimiento.'
                        ]
                    ],
                ], 422);
            }


            /*
            |--------------------------------------------------------------------------
            | LA CUENTA DEBE ESTAR ACTIVA
            |--------------------------------------------------------------------------
            */

            if (!$account->status) {

                return response()->json([
                    'success' => false,
                    'message' => 'La cuenta contable seleccionada está inactiva.',
                    'errors' => [
                        'accounting_account_id' => [
                            'Debe seleccionar una cuenta contable activa.'
                        ]
                    ],
                ], 422);
            }


            /*
            |--------------------------------------------------------------------------
            | VALIDAR TIPO CONTABLE
            |--------------------------------------------------------------------------
            */

            $accountType = $account->accountType;

            if (!$accountType) {

                return response()->json([
                    'success' => false,
                    'message' => 'La cuenta contable no tiene un tipo contable válido.',
                    'errors' => [
                        'accounting_account_id' => [
                            'La cuenta no tiene tipo contable.'
                        ]
                    ],
                ], 422);
            }


            /*
            |--------------------------------------------------------------------------
            | INGRESO
            |--------------------------------------------------------------------------
            |
            | INGRESO -> cuenta tipo ING
            |
            */

            if (
                $validated['type'] === 'INGRESO'
                && $accountType->code !== 'ING'
            ) {

                return response()->json([
                    'success' => false,
                    'message' => 'La cuenta seleccionada no corresponde a un ingreso.',
                    'errors' => [
                        'accounting_account_id' => [
                            'Una categoría de INGRESO debe utilizar una cuenta de tipo INGRESO.'
                        ]
                    ],
                ], 422);
            }


            /*
            |--------------------------------------------------------------------------
            | EGRESO
            |--------------------------------------------------------------------------
            |
            | EGRESO -> GASTO o COSTO
            |
            */

            if (
                $validated['type'] === 'EGRESO'
                && !in_array(
                    $accountType->code,
                    ['GAS', 'COS'],
                    true
                )
            ) {

                return response()->json([
                    'success' => false,
                    'message' => 'La cuenta seleccionada no corresponde a un egreso.',
                    'errors' => [
                        'accounting_account_id' => [
                            'Una categoría de EGRESO debe utilizar una cuenta de GASTO o COSTO.'
                        ]
                    ],
                ], 422);
            }


            /*
            |--------------------------------------------------------------------------
            | GENERAR CÓDIGO
            |--------------------------------------------------------------------------
            |
            | Ejemplo:
            |
            | ING-0001
            | ING-0002
            |
            | EGR-0001
            | EGR-0002
            |
            */

            $prefix = $validated['type'] === 'INGRESO'
                ? 'ING'
                : 'EGR';


            $lastCategory = CashCategory::query()
                ->where('id_company', $user->id_company)
                ->where('type', $validated['type'])
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();


            $nextNumber = 1;

            if ($lastCategory && preg_match(
                '/(\d+)$/',
                $lastCategory->code,
                $matches
            )) {

                $nextNumber = ((int) $matches[1]) + 1;
            }


            $code = sprintf(
                '%s-%04d',
                $prefix,
                $nextNumber
            );


            /*
            |--------------------------------------------------------------------------
            | CREAR
            |--------------------------------------------------------------------------
            */

            $category = CashCategory::create([
                'id_company' => $user->id_company,
                'code' => $code,
                'name' => trim($validated['name']),
                'type' => $validated['type'],
                'accounting_account_id' => $account->id,
                'status' => $validated['status'] ?? true,
            ]);


            /*
            |--------------------------------------------------------------------------
            | CARGAR RELACIONES
            |--------------------------------------------------------------------------
            */

            $category->load([
                'accountingAccount.accountType',
                'accountingAccount.parent',
            ]);


            return response()->json([
                'success' => true,
                'message' => 'Categoría creada correctamente.',
                'data' => $category,
            ], 201);
        });
    }


    /**
     * ============================================================
     * ACTUALIZAR
     * ============================================================
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();

        $category = CashCategory::query()
            ->where('id_company', $user->id_company)
            ->findOrFail($id);


        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'type' => [
                'required',
                'string',
                Rule::in([
                    'INGRESO',
                    'EGRESO',
                ]),
            ],

            'accounting_account_id' => [
                'required',
                'integer',
            ],

            'status' => [
                'nullable',
                'boolean',
            ],
        ]);


        return DB::transaction(function () use (
            $user,
            $category,
            $validated
        ) {

            /*
            |--------------------------------------------------------------------------
            | OBTENER CUENTA
            |--------------------------------------------------------------------------
            */

            $account = AccountingAccount::query()
                ->where('id_company', $user->id_company)
                ->where('id', $validated['accounting_account_id'])
                ->with('accountType')
                ->first();


            if (!$account) {

                return response()->json([
                    'success' => false,
                    'message' => 'La cuenta contable seleccionada no pertenece a la empresa.',
                    'errors' => [
                        'accounting_account_id' => [
                            'La cuenta contable seleccionada no es válida.'
                        ]
                    ],
                ], 422);
            }


            /*
            |--------------------------------------------------------------------------
            | CUENTA DE MOVIMIENTO
            |--------------------------------------------------------------------------
            */

            if (!$account->allows_entries) {

                return response()->json([
                    'success' => false,
                    'message' => 'La cuenta seleccionada no permite registros contables.',
                    'errors' => [
                        'accounting_account_id' => [
                            'Debe seleccionar una cuenta de movimiento.'
                        ]
                    ],
                ], 422);
            }


            /*
            |--------------------------------------------------------------------------
            | CUENTA ACTIVA
            |--------------------------------------------------------------------------
            */

            if (!$account->status) {

                return response()->json([
                    'success' => false,
                    'message' => 'La cuenta contable seleccionada está inactiva.',
                    'errors' => [
                        'accounting_account_id' => [
                            'Debe seleccionar una cuenta contable activa.'
                        ]
                    ],
                ], 422);
            }


            /*
            |--------------------------------------------------------------------------
            | TIPO CONTABLE
            |--------------------------------------------------------------------------
            */

            $accountType = $account->accountType;

            if (!$accountType) {

                return response()->json([
                    'success' => false,
                    'message' => 'La cuenta contable no tiene tipo contable.',
                    'errors' => [
                        'accounting_account_id' => [
                            'La cuenta no tiene tipo contable.'
                        ]
                    ],
                ], 422);
            }


            /*
            |--------------------------------------------------------------------------
            | VALIDAR INGRESO
            |--------------------------------------------------------------------------
            */

            if (
                $validated['type'] === 'INGRESO'
                && $accountType->code !== 'ING'
            ) {

                return response()->json([
                    'success' => false,
                    'message' => 'La cuenta seleccionada no corresponde a un ingreso.',
                    'errors' => [
                        'accounting_account_id' => [
                            'Una categoría de INGRESO debe utilizar una cuenta de tipo INGRESO.'
                        ]
                    ],
                ], 422);
            }


            /*
            |--------------------------------------------------------------------------
            | VALIDAR EGRESO
            |--------------------------------------------------------------------------
            */

            if (
                $validated['type'] === 'EGRESO'
                && !in_array(
                    $accountType->code,
                    ['GAS', 'COS'],
                    true
                )
            ) {

                return response()->json([
                    'success' => false,
                    'message' => 'La cuenta seleccionada no corresponde a un egreso.',
                    'errors' => [
                        'accounting_account_id' => [
                            'Una categoría de EGRESO debe utilizar una cuenta de GASTO o COSTO.'
                        ]
                    ],
                ], 422);
            }


            /*
            |--------------------------------------------------------------------------
            | ACTUALIZAR
            |--------------------------------------------------------------------------
            */

            $category->update([
                'name' => trim($validated['name']),
                'type' => $validated['type'],
                'accounting_account_id' => $account->id,
                'status' => $validated['status'] ?? $category->status,
            ]);


            $category->load([
                'accountingAccount.accountType',
                'accountingAccount.parent',
            ]);


            return response()->json([
                'success' => true,
                'message' => 'Categoría actualizada correctamente.',
                'data' => $category,
            ]);
        });
    }


    /**
     * ============================================================
     * ACTIVAR / DESACTIVAR
     * ============================================================
     */
    public function toggleStatus(Request $request, $id)
    {
        $user = $request->user();

        $category = CashCategory::query()
            ->where('id_company', $user->id_company)
            ->findOrFail($id);


        $category->update([
            'status' => !$category->status,
        ]);


        $category->load([
            'accountingAccount.accountType',
        ]);


        return response()->json([
            'success' => true,
            'message' => $category->status
                ? 'Categoría activada correctamente.'
                : 'Categoría desactivada correctamente.',
            'data' => $category,
        ]);
    }


    /**
     * ============================================================
     * ELIMINAR
     * ============================================================
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        $category = CashCategory::query()
            ->where('id_company', $user->id_company)
            ->findOrFail($id);


        /*
        |--------------------------------------------------------------------------
        | NO ELIMINAR SI YA TIENE MOVIMIENTOS
        |--------------------------------------------------------------------------
        */

        if ($category->movements()->exists()) {

            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar la categoría porque ya tiene movimientos de caja asociados.',
            ], 422);
        }


        $category->delete();


        return response()->json([
            'success' => true,
            'message' => 'Categoría eliminada correctamente.',
        ]);
    }
}