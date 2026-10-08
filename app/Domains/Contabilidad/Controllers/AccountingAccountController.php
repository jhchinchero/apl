<?php

namespace App\Domains\Contabilidad\Controllers;




use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use App\Domains\Contabilidad\Models\AccountingAccount;
use App\Domains\Contabilidad\Models\AccountingAccountType;

class AccountingAccountController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | LISTAR CUENTAS
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $idCompany = $user->id_company;

        $accounts = AccountingAccount::query()
            ->where('id_company', $idCompany)
            ->with([
                'accountType:id,code,name,nature',
            ])
            ->orderBy('code')
            ->get();

        $data = $accounts->map(function ($account) {

            return [
                'id' => $account->id,

                'code' => $account->code,

                'name' => $account->name,

                'type' => $account->accountType?->code,

                'account_type_id' => $account->account_type_id,

                'parent_id' => $account->parent_id,

                'level' => $account->level,

                'allows_entries' => (bool) $account->allows_entries,

                'status' => (bool) $account->status,

                'description' => $account->description ?? '',
            ];

        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | TIPOS CONTABLES
    |--------------------------------------------------------------------------
    */

    public function types()
    {
        $types = AccountingAccountType::query()
            ->where('status', true)
            ->orderBy('id')
            ->get([
                'id',
                'code',
                'name',
                'nature',
                'description',
            ]);

        return response()->json([
            'success' => true,
            'data' => $types,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | MOSTRAR CUENTA
    |--------------------------------------------------------------------------
    */

    public function show(Request $request, int $id)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $account = AccountingAccount::query()
            ->where('id_company', $user->id_company)
            ->with([
                'accountType',
                'parent',
                'children',
            ])
            ->find($id);

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Cuenta contable no encontrada.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $account,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | CREAR CUENTA
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $idCompany = $user->id_company;

        $validated = $request->validate([
            'account_type_id' => [
                'required',
                'integer',
                'exists:accounting_account_types,id',
            ],

            'parent_id' => [
                'nullable',
                'integer',
            ],

            'code' => [
                'required',
                'string',
                'max:30',
            ],

            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'allows_entries' => [
                'required',
                'boolean',
            ],

            'status' => [
                'nullable',
                'boolean',
            ],

            'description' => [
                'nullable',
                'string',
            ],
        ]);

        return DB::transaction(function () use (
            $validated,
            $idCompany
        ) {

            /*
            |--------------------------------------------------------------------------
            | Validar tipo
            |--------------------------------------------------------------------------
            */

            $type = AccountingAccountType::query()
                ->where('id', $validated['account_type_id'])
                ->where('status', true)
                ->first();

            if (!$type) {
                throw ValidationException::withMessages([
                    'account_type_id' =>
                        'El tipo contable no existe o está inactivo.',
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | Validar código único dentro de empresa
            |--------------------------------------------------------------------------
            */

            $codeExists = AccountingAccount::query()
                ->where('id_company', $idCompany)
                ->where(
                    'code',
                    strtoupper(trim($validated['code']))
                )
                ->exists();

            if ($codeExists) {
                throw ValidationException::withMessages([
                    'code' =>
                        'El código ya existe dentro de esta empresa.',
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | Padre
            |--------------------------------------------------------------------------
            */

            $parent = null;

            if (!empty($validated['parent_id'])) {

                $parent = AccountingAccount::query()
                    ->where('id_company', $idCompany)
                    ->find($validated['parent_id']);

                if (!$parent) {
                    throw ValidationException::withMessages([
                        'parent_id' =>
                            'La cuenta padre no pertenece a esta empresa.',
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | El padre debe ser agrupador
                |--------------------------------------------------------------------------
                */

                if ($parent->allows_entries) {

                    throw ValidationException::withMessages([
                        'parent_id' =>
                            'Una cuenta de movimiento no puede tener subcuentas.',
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | El padre debe estar activo
                |--------------------------------------------------------------------------
                */

                if (!$parent->status) {

                    throw ValidationException::withMessages([
                        'parent_id' =>
                            'No se puede utilizar una cuenta padre inactiva.',
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | Mismo tipo contable
                |--------------------------------------------------------------------------
                */

                if (
                    (int) $parent->account_type_id !==
                    (int) $validated['account_type_id']
                ) {

                    throw ValidationException::withMessages([
                        'parent_id' =>
                            'La cuenta padre debe pertenecer al mismo tipo contable.',
                    ]);
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Nivel
            |--------------------------------------------------------------------------
            */

            $level = $parent
                ? ((int) $parent->level + 1)
                : 1;


            /*
            |--------------------------------------------------------------------------
            | Si tiene padre, validar estructura del código
            |--------------------------------------------------------------------------
            */

            $code = strtoupper(
                trim($validated['code'])
            );

            if ($parent) {

                $expectedPrefix =
                    $parent->code . '.';

                if (
                    !str_starts_with(
                        $code,
                        $expectedPrefix
                    )
                ) {

                    throw ValidationException::withMessages([
                        'code' =>
                            "El código debe comenzar con {$expectedPrefix}.",
                    ]);
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Crear
            |--------------------------------------------------------------------------
            */

            $account = AccountingAccount::create([

                'id_company' =>
                    $idCompany,

                'account_type_id' =>
                    $validated['account_type_id'],

                'parent_id' =>
                    $parent?->id,

                'code' =>
                    $code,

                'name' =>
                    strtoupper(
                        trim($validated['name'])
                    ),

                'level' =>
                    $level,

                'allows_entries' =>
                    (bool) $validated['allows_entries'],

                'status' =>
                    array_key_exists(
                        'status',
                        $validated
                    )
                        ? (bool) $validated['status']
                        : true,

                'description' =>
                    isset($validated['description'])
                        ? trim($validated['description'])
                        : null,
            ]);

            $account->load([
                'accountType',
                'parent',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cuenta creada correctamente.',
                'data' => $account,
            ], 201);
        });
    }


    /*
    |--------------------------------------------------------------------------
    | ACTUALIZAR CUENTA
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        int $id
    ) {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $idCompany = $user->id_company;

        $account = AccountingAccount::query()
            ->where('id_company', $idCompany)
            ->find($id);

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Cuenta contable no encontrada.',
            ], 404);
        }

        $validated = $request->validate([
            'account_type_id' => [
                'required',
                'integer',
                'exists:accounting_account_types,id',
            ],

            'parent_id' => [
                'nullable',
                'integer',
            ],

            'code' => [
                'required',
                'string',
                'max:30',
            ],

            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'allows_entries' => [
                'required',
                'boolean',
            ],

            'status' => [
                'nullable',
                'boolean',
            ],

            'description' => [
                'nullable',
                'string',
            ],
        ]);

        return DB::transaction(function () use (
            $validated,
            $account,
            $idCompany
        ) {

            /*
            |--------------------------------------------------------------------------
            | Tipo
            |--------------------------------------------------------------------------
            */

            $type = AccountingAccountType::query()
                ->where('id', $validated['account_type_id'])
                ->where('status', true)
                ->first();

            if (!$type) {
                throw ValidationException::withMessages([
                    'account_type_id' =>
                        'El tipo contable no existe o está inactivo.',
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | Código
            |--------------------------------------------------------------------------
            */

            $code = strtoupper(
                trim($validated['code'])
            );

            $codeExists = AccountingAccount::query()
                ->where('id_company', $idCompany)
                ->where('code', $code)
                ->where('id', '!=', $account->id)
                ->exists();

            if ($codeExists) {

                throw ValidationException::withMessages([
                    'code' =>
                        'El código ya pertenece a otra cuenta.',
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | No permitir que sea su propio padre
            |--------------------------------------------------------------------------
            */

            if (
                !empty($validated['parent_id']) &&
                (int) $validated['parent_id'] ===
                (int) $account->id
            ) {

                throw ValidationException::withMessages([
                    'parent_id' =>
                        'Una cuenta no puede ser padre de sí misma.',
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | Obtener padre
            |--------------------------------------------------------------------------
            */

            $parent = null;

            if (!empty($validated['parent_id'])) {

                $parent = AccountingAccount::query()
                    ->where('id_company', $idCompany)
                    ->find($validated['parent_id']);

                if (!$parent) {

                    throw ValidationException::withMessages([
                        'parent_id' =>
                            'La cuenta padre no pertenece a esta empresa.',
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | Padre debe ser agrupador
                |--------------------------------------------------------------------------
                */

                if ($parent->allows_entries) {

                    throw ValidationException::withMessages([
                        'parent_id' =>
                            'Una cuenta de movimiento no puede tener subcuentas.',
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | Padre activo
                |--------------------------------------------------------------------------
                */

                if (!$parent->status) {

                    throw ValidationException::withMessages([
                        'parent_id' =>
                            'No se puede utilizar una cuenta padre inactiva.',
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | Mismo tipo
                |--------------------------------------------------------------------------
                */

                if (
                    (int) $parent->account_type_id !==
                    (int) $validated['account_type_id']
                ) {

                    throw ValidationException::withMessages([
                        'parent_id' =>
                            'La cuenta padre debe pertenecer al mismo tipo contable.',
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | No permitir colocarla debajo de un descendiente
                |--------------------------------------------------------------------------
                */

                if (
                    $this->isDescendant(
                        $account,
                        $parent->id,
                        $idCompany
                    )
                ) {

                    throw ValidationException::withMessages([
                        'parent_id' =>
                            'No se puede colocar la cuenta debajo de una de sus propias subcuentas.',
                    ]);
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Hijos actuales
            |--------------------------------------------------------------------------
            */

            $hasChildren = AccountingAccount::query()
                ->where(
                    'parent_id',
                    $account->id
                )
                ->exists();


            /*
            |--------------------------------------------------------------------------
            | Cuenta con hijos debe ser agrupadora
            |--------------------------------------------------------------------------
            */

            if (
                $hasChildren &&
                (bool) $validated['allows_entries'] === true
            ) {

                throw ValidationException::withMessages([
                    'allows_entries' =>
                        'Una cuenta que tiene subcuentas debe ser agrupadora.',
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | Si la cuenta ya es movimiento y tiene hijos
            |--------------------------------------------------------------------------
            */

            if (
                $account->allows_entries &&
                $hasChildren &&
                (bool) $validated['allows_entries']
            ) {

                throw ValidationException::withMessages([
                    'allows_entries' =>
                        'La cuenta tiene subcuentas y no puede ser de movimiento.',
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | Nivel
            |--------------------------------------------------------------------------
            */

            $level = $parent
                ? ((int) $parent->level + 1)
                : 1;


            /*
            |--------------------------------------------------------------------------
            | Validar prefijo del código
            |--------------------------------------------------------------------------
            */

            if ($parent) {

                $prefix =
                    $parent->code . '.';

                if (
                    !str_starts_with(
                        $code,
                        $prefix
                    )
                ) {

                    throw ValidationException::withMessages([
                        'code' =>
                            "El código debe comenzar con {$prefix}.",
                    ]);
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Actualizar
            |--------------------------------------------------------------------------
            */

            $account->update([

                'account_type_id' =>
                    $validated['account_type_id'],

                'parent_id' =>
                    $parent?->id,

                'code' =>
                    $code,

                'name' =>
                    strtoupper(
                        trim($validated['name'])
                    ),

                'level' =>
                    $level,

                'allows_entries' =>
                    (bool) $validated['allows_entries'],

                'status' =>
                    array_key_exists(
                        'status',
                        $validated
                    )
                        ? (bool) $validated['status']
                        : $account->status,

                'description' =>
                    isset($validated['description'])
                        ? trim($validated['description'])
                        : null,
            ]);


            /*
            |--------------------------------------------------------------------------
            | Recalcular niveles de hijos
            |--------------------------------------------------------------------------
            */

            $this->recalculateChildrenLevels(
                $account,
                $idCompany
            );


            $account->refresh();

            $account->load([
                'accountType',
                'parent',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cuenta actualizada correctamente.',
                'data' => $account,
            ]);
        });
    }


    /*
    |--------------------------------------------------------------------------
    | ACTIVAR / DESACTIVAR
    |--------------------------------------------------------------------------
    */

    public function toggleStatus(
        Request $request,
        int $id
    ) {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $account = AccountingAccount::query()
            ->where(
                'id_company',
                $user->id_company
            )
            ->find($id);

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Cuenta no encontrada.',
            ], 404);
        }


        /*
        |--------------------------------------------------------------------------
        | No desactivar si tiene hijos activos
        |--------------------------------------------------------------------------
        */

        if ($account->status) {

            $hasActiveChildren =
                AccountingAccount::query()
                    ->where(
                        'parent_id',
                        $account->id
                    )
                    ->where(
                        'status',
                        true
                    )
                    ->exists();

            if ($hasActiveChildren) {

                return response()->json([
                    'success' => false,
                    'message' =>
                        'No se puede desactivar una cuenta que tiene subcuentas activas.',
                ], 422);
            }
        }


        $account->status =
            !$account->status;

        $account->save();

        return response()->json([
            'success' => true,
            'message' =>
                $account->status
                    ? 'Cuenta activada correctamente.'
                    : 'Cuenta desactivada correctamente.',
            'data' => [
                'id' => $account->id,
                'status' => (bool) $account->status,
            ],
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | ELIMINAR
    |--------------------------------------------------------------------------
    */

    public function destroy(
        Request $request,
        int $id
    ) {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $account = AccountingAccount::query()
            ->where(
                'id_company',
                $user->id_company
            )
            ->find($id);

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Cuenta no encontrada.',
            ], 404);
        }


        /*
        |--------------------------------------------------------------------------
        | No eliminar si tiene hijos
        |--------------------------------------------------------------------------
        */

        $hasChildren =
            AccountingAccount::query()
                ->where(
                    'parent_id',
                    $account->id
                )
                ->exists();

        if ($hasChildren) {

            return response()->json([
                'success' => false,
                'message' =>
                    'No se puede eliminar una cuenta que tiene subcuentas.',
            ], 422);
        }


        /*
        |--------------------------------------------------------------------------
        | IMPORTANTE
        |
        | Más adelante aquí también validaremos si la cuenta
        | ya tiene asientos contables.
        |--------------------------------------------------------------------------
        */

        $account->delete();

        return response()->json([
            'success' => true,
            'message' =>
                'Cuenta eliminada correctamente.',
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | VERIFICAR SI UN NODO ES DESCENDIENTE
    |--------------------------------------------------------------------------
    */

    private function isDescendant(
        AccountingAccount $account,
        int $possibleParentId,
        string $idCompany
    ): bool {

        $currentId = $possibleParentId;

        while ($currentId) {

            if (
                (int) $currentId ===
                (int) $account->id
            ) {
                return true;
            }

            $parent = AccountingAccount::query()
                ->where(
                    'id_company',
                    $idCompany
                )
                ->where(
                    'id',
                    $currentId
                )
                ->first();

            if (!$parent) {
                break;
            }

            $currentId = $parent->parent_id;
        }

        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | RECALCULAR NIVELES
    |--------------------------------------------------------------------------
    */

    private function recalculateChildrenLevels(
        AccountingAccount $parent,
        string $idCompany
    ): void {

        $children = AccountingAccount::query()
            ->where(
                'id_company',
                $idCompany
            )
            ->where(
                'parent_id',
                $parent->id
            )
            ->get();

        foreach ($children as $child) {

            $child->level =
                ((int) $parent->level + 1);

            $child->save();

            $this->recalculateChildrenLevels(
                $child,
                $idCompany
            );
        }
    }
}