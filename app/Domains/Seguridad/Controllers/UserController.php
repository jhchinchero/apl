<?php

namespace App\Domains\Seguridad\Controllers;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

use App\Domains\Seguridad\Models\User;
use App\Domains\Empresa\Models\Branch;
use App\Domains\Empresa\Models\PointOfSale;

class UserController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | CREAR USUARIO
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $authUser = $request->user();

        if (!$authUser) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.'
            ], 401);
        }

        $companyId = $authUser->id_company;

        /*
        |--------------------------------------------------------------------------
        | VALIDACIÓN
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([

            'name' => [
                'required',
                'string',
                'max:255'
            ],

            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')
                    ->where(function ($query) use ($companyId) {
                        return $query->where(
                            'id_company',
                            $companyId
                        );
                    })
            ],

            'phone' => [
                'nullable',
                'string',
                'max:50'
            ],

            'address' => [
                'nullable',
                'string',
                'max:255'
            ],

            'reference_person' => [
                'nullable',
                'string',
                'max:255'
            ],

            'password' => [
                'required',
                'string',
                'min:6',
                'confirmed'
            ],

            'role_id' => [
                'required',
                'integer',
                'exists:roles,id'
            ],

            /*
            |--------------------------------------------------------------------------
            | SUCURSALES
            |--------------------------------------------------------------------------
            */

            'branches' => [
                'required',
                'array',
                'min:1'
            ],

            'branches.*.branch_id' => [
                'required',
                'integer',
                'exists:branches,id'
            ],

            /*
            |--------------------------------------------------------------------------
            | MÚLTIPLES POS
            |--------------------------------------------------------------------------
            */

            'branches.*.point_of_sale_ids' => [
                'required',
                'array',
                'min:1'
            ],
 
            'branches.*.point_of_sale_ids.*' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) use ($companyId) {
                    $pos = PointOfSale::where('id', $value)
                        ->where('id_company', $companyId)
                        ->where('status', true)
                        ->first();
 
                    if (!$pos) {
                        $fail("El punto de venta $value no existe o está inactivo.");
                    }
                }
            ]
        ]);

        DB::beginTransaction();

        try {

            /*
            |--------------------------------------------------------------------------
            | CREAR USUARIO
            |--------------------------------------------------------------------------
            */

            $user = User::create([

                'id_company' =>
                    $companyId,

                'name' =>
                    $validated['name'],

                'email' =>
                    $validated['email'],

                'phone' =>
                    $validated['phone'] ?? null,

                'address' =>
                    $validated['address'] ?? null,

                'reference_person' =>
                    $validated['reference_person'] ?? null,

                'password' =>
                    Hash::make(
                        $validated['password']
                    ),

                'role_id' =>
                    $validated['role_id'],
            ]);

            /*
            |--------------------------------------------------------------------------
            | ASIGNAR SUCURSALES + POS
            |--------------------------------------------------------------------------
            */

            foreach (
                $validated['branches']
                as $branchData
            ) {

                /*
                |--------------------------------------------------------------------------
                | VALIDAR SUCURSAL
                |--------------------------------------------------------------------------
                */

                $branch = Branch::where(
                    'id',
                    $branchData['branch_id']
                )
                    ->where(
                        'id_company',
                        $companyId
                    )
                    ->where(
                        'status',
                        true
                    )
                    ->first();

                if (!$branch) {

                    throw new \Exception(
                        'La sucursal seleccionada no pertenece a la empresa o está inactiva.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | QUITAR POS DUPLICADOS
                |--------------------------------------------------------------------------
                */

                $pointOfSaleIds = array_values(
                    array_unique(
                        array_map(
                            'intval',
                            $branchData['point_of_sale_ids']
                        )
                    )
                );

                /*
                |--------------------------------------------------------------------------
                | VALIDAR Y GUARDAR CADA POS
                |--------------------------------------------------------------------------
                */

                foreach (
                    $pointOfSaleIds
                    as $pointOfSaleId
                ) {

                    $pointOfSale =
                        PointOfSale::where(
                            'id',
                            $pointOfSaleId
                        )
                            ->where(
                                'branch_id',
                                $branch->id
                            )
                            ->where(
                                'id_company',
                                $companyId
                            )
                            ->where(
                                'status',
                                true
                            )
                            ->first();

                    if (!$pointOfSale) {

                        throw new \Exception(
                            "El punto de venta ID {$pointOfSaleId} no pertenece a la sucursal {$branch->branch_name}."
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | UNA FILA POR POS
                    |--------------------------------------------------------------------------
                    */

                    DB::table('user_branches')->insert([

                        'user_id' =>
                            $user->id,

                        'branch_id' =>
                            $branch->id,

                        'point_of_sale_id' =>
                            $pointOfSale->id,

                        'created_at' =>
                            now(),

                        'updated_at' =>
                            now(),
                    ]);
                }
            }

            DB::commit();

            /*
            |--------------------------------------------------------------------------
            | CARGAR USUARIO
            |--------------------------------------------------------------------------
            */

            $user->load([
                'role',
                'company'
            ]);

            /*
            |--------------------------------------------------------------------------
            | RESPUESTA
            |--------------------------------------------------------------------------
            */

            return response()->json([

                'success' => true,

                'message' =>
                    'Usuario creado correctamente.',

                'data' =>
                    $user

            ], 201);

        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error(
                'Error al crear usuario',
                [
                    'company_id' =>
                        $companyId,

                    'error' =>
                        $e->getMessage(),

                    'line' =>
                        $e->getLine(),

                    'file' =>
                        $e->getFile()
                ]
            );

            return response()->json([

                'success' => false,

                'message' =>
                    $e->getMessage()

            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | LISTAR USUARIOS
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        try {

            /*
            |--------------------------------------------------------------------------
            | USUARIO AUTENTICADO
            |--------------------------------------------------------------------------
            */

            $authUser = $request->user();

            if (!$authUser) {

                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado.'
                ], 401);
            }

            /*
            |--------------------------------------------------------------------------
            | SOLO ADMINISTRADOR
            |--------------------------------------------------------------------------
            */

            if ((int) $authUser->role_id !== 1) {

                return response()->json([
                    'success' => false,
                    'message' =>
                        'No tiene permiso para visualizar los usuarios.'
                ], 403);
            }

            /*
            |--------------------------------------------------------------------------
            | EMPRESA
            |--------------------------------------------------------------------------
            */

            $companyId =
                $authUser->id_company;

            if (!$companyId) {

                return response()->json([
                    'success' => false,
                    'message' =>
                        'El usuario no pertenece a ninguna empresa.'
                ], 400);
            }

            /*
            |--------------------------------------------------------------------------
            | CONSULTA
            |--------------------------------------------------------------------------
            */

            $query = User::with([
                'role',
                'company',
                'accesses'
            ])
                ->where(
                    'id_company',
                    $companyId
                );

            /*
            |--------------------------------------------------------------------------
            | OBTENER USUARIOS
            |--------------------------------------------------------------------------
            */

            $users = $query
                ->orderBy('name')
                ->get();

            /*
            |--------------------------------------------------------------------------
            | CONSTRUIR ASIGNACIONES
            |--------------------------------------------------------------------------
            |
            | NO usamos user->branches() porque ahora una sucursal
            | puede aparecer varias veces con diferentes POS.
            |
            */

            $users->each(function ($user) {

                $assignments =
                    DB::table('user_branches')
                        ->join(
                            'branches',
                            'branches.id',
                            '=',
                            'user_branches.branch_id'
                        )
                        ->join(
                            'point_of_sales',
                            'point_of_sales.id',
                            '=',
                            'user_branches.point_of_sale_id'
                        )
                        ->where(
                            'user_branches.user_id',
                            $user->id
                        )
                        ->select([
                            'branches.id as branch_id',
                            'branches.code_branch',
                            'branches.branch_name',

                            'point_of_sales.id as point_of_sale_id',
                            'point_of_sales.codigo as point_of_sale_code',
                            'point_of_sales.name as point_of_sale_name'
                        ])
                        ->orderBy(
                            'branches.branch_name'
                        )
                        ->orderBy(
                            'point_of_sales.name'
                        )
                        ->get();

                /*
                |--------------------------------------------------------------------------
                | AGRUPAR POR SUCURSAL
                |--------------------------------------------------------------------------
                */

                $grouped =
                    $assignments
                        ->groupBy('branch_id')
                        ->map(function ($items) {

                            $first =
                                $items->first();

                            return [

                                'id' =>
                                    $first->branch_id,

                                'code_branch' =>
                                    $first->code_branch,

                                'branch_name' =>
                                    $first->branch_name,

                                'point_of_sales' =>
                                    $items->map(function ($pos) {

                                        return [

                                            'id' =>
                                                $pos->point_of_sale_id,

                                            'codigo' =>
                                                $pos->point_of_sale_code,

                                            'name' =>
                                                $pos->point_of_sale_name
                                        ];

                                    })->values()->all()
                            ];
                        })
                        ->values()
                        ->all();

                /*
                |--------------------------------------------------------------------------
                | AGREGAR AL USUARIO
                |--------------------------------------------------------------------------
                */

                $user->setAttribute(
                    'branches',
                    $grouped
                );
            });

            /*
            |--------------------------------------------------------------------------
            | RESPUESTA
            |--------------------------------------------------------------------------
            */

            return response()->json([

                'success' => true,

                'message' =>
                    'Usuarios obtenidos correctamente.',

                'data' =>
                    $users

            ]);

        } catch (\Throwable $e) {

            Log::error(
                'Error al obtener usuarios',
                [
                    'error' =>
                        $e->getMessage(),

                    'line' =>
                        $e->getLine(),

                    'file' =>
                        $e->getFile()
                ]
            );

            return response()->json([

                'success' => false,

                'message' =>
                    'Error al obtener los usuarios.',

                'error' =>
                    $e->getMessage()

            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | ACTUALIZAR USUARIO
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        $id
    ) {

        $authUser = $request->user();

        if (!$authUser) {

            return response()->json([
                'success' => false,
                'message' =>
                    'Usuario no autenticado.'
            ], 401);
        }

        $companyId =
            $authUser->id_company;

        if (!$companyId) {

            return response()->json([
                'success' => false,
                'message' =>
                    'El usuario autenticado no pertenece a ninguna empresa.'
            ], 400);
        }

        /*
        |--------------------------------------------------------------------------
        | BUSCAR USUARIO
        |--------------------------------------------------------------------------
        */

        $user = User::where(
            'id',
            $id
        )
            ->where(
                'id_company',
                $companyId
            )
            ->first();

        if (!$user) {

            return response()->json([
                'success' => false,
                'message' =>
                    'Usuario no encontrado o no pertenece a su empresa.'
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | VALIDACIÓN
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([

            'name' =>
                'required|string|max:255',

            'email' => [
                'required',
                'email',
                'max:255',

                Rule::unique(
                    'users',
                    'email'
                )
                    ->where(function ($query) use ($companyId) {

                        return $query->where(
                            'id_company',
                            $companyId
                        );
                    })
                    ->ignore($user->id),
            ],

            'phone' =>
                'nullable|string|max:50',

            'address' =>
                'nullable|string|max:255',

            'reference_person' =>
                'nullable|string|max:255',

            'role_id' =>
                'required|integer|exists:roles,id',

            'password' =>
                'nullable|string|min:6|confirmed',

            /*
            |--------------------------------------------------------------------------
            | SUCURSALES
            |--------------------------------------------------------------------------
            */

            'branches' => [
                'required',
                'array',
                'min:1'
            ],

            'branches.*.branch_id' => [
                'required',
                'integer',
                'exists:branches,id'
            ],

            /*
            |--------------------------------------------------------------------------
            | MÚLTIPLES POS
            |--------------------------------------------------------------------------
            */

            'branches.*.point_of_sale_ids' => [
                'required',
                'array',
                'min:1'
            ],

            'branches.*.point_of_sale_ids.*' => [
                'required',
                'integer',
                'exists:point_of_sales,id'
            ],
        ]);

        DB::beginTransaction();

        try {

            /*
            |--------------------------------------------------------------------------
            | ACTUALIZAR DATOS
            |--------------------------------------------------------------------------
            */

            $user->name =
                $validated['name'];

            $user->email =
                $validated['email'];

            $user->phone =
                $validated['phone'] ?? null;

            $user->address =
                $validated['address'] ?? null;

            $user->reference_person =
                $validated['reference_person'] ?? null;

            $user->role_id =
                $validated['role_id'];

            /*
            |--------------------------------------------------------------------------
            | CONTRASEÑA
            |--------------------------------------------------------------------------
            */

            if (
                !empty(
                    $validated['password']
                )
            ) {

                $user->password =
                    Hash::make(
                        $validated['password']
                    );
            }

            $user->save();

            /*
            |--------------------------------------------------------------------------
            | ELIMINAR ASIGNACIONES ANTERIORES
            |--------------------------------------------------------------------------
            |
            | Esto es importante.
            |
            | Como ahora podemos tener:
            |
            | usuario + sucursal + POS
            |
            | no usamos sync().
            |
            */

            DB::table('user_branches')
                ->where(
                    'user_id',
                    $user->id
                )
                ->delete();

            /*
            |--------------------------------------------------------------------------
            | CREAR NUEVAS ASIGNACIONES
            |--------------------------------------------------------------------------
            */

            foreach (
                $validated['branches']
                as $branchData
            ) {

                /*
                |--------------------------------------------------------------------------
                | SUCURSAL
                |--------------------------------------------------------------------------
                */

                $branch =
                    Branch::where(
                        'id',
                        $branchData['branch_id']
                    )
                        ->where(
                            'id_company',
                            $companyId
                        )
                        ->where(
                            'status',
                            true
                        )
                        ->first();

                if (!$branch) {

                    throw new \Exception(
                        'La sucursal seleccionada no pertenece a su empresa o está inactiva.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | POS
                |--------------------------------------------------------------------------
                */

                $pointOfSaleIds =
                    array_values(
                        array_unique(
                            array_map(
                                'intval',
                                $branchData[
                                    'point_of_sale_ids'
                                ]
                            )
                        )
                    );

                foreach (
                    $pointOfSaleIds
                    as $pointOfSaleId
                ) {

                    /*
                    |--------------------------------------------------------------------------
                    | VALIDAR POS
                    |--------------------------------------------------------------------------
                    */

                    $pointOfSale =
                        PointOfSale::where(
                            'id',
                            $pointOfSaleId
                        )
                            ->where(
                                'branch_id',
                                $branch->id
                            )
                            ->where(
                                'id_company',
                                $companyId
                            )
                            ->where(
                                'status',
                                true
                            )
                            ->first();

                    if (!$pointOfSale) {

                        throw new \Exception(
                            "El punto de venta ID {$pointOfSaleId} no pertenece a la sucursal {$branch->branch_name}."
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | INSERTAR AUTORIZACIÓN
                    |--------------------------------------------------------------------------
                    */

                    DB::table(
                        'user_branches'
                    )->insert([

                        'user_id' =>
                            $user->id,

                        'branch_id' =>
                            $branch->id,

                        'point_of_sale_id' =>
                            $pointOfSale->id,

                        'created_at' =>
                            now(),

                        'updated_at' =>
                            now(),
                    ]);
                }
            }

            DB::commit();

            /*
            |--------------------------------------------------------------------------
            | RECARGAR USUARIO
            |--------------------------------------------------------------------------
            */

            $user->load([
                'role',
                'company'
            ]);

            /*
            |--------------------------------------------------------------------------
            | RESPUESTA
            |--------------------------------------------------------------------------
            */

            return response()->json([

                'success' => true,

                'message' =>
                    'Usuario actualizado correctamente.',

                'data' =>
                    $user

            ], 200);

        } catch (
            \Illuminate\Validation\ValidationException $e
        ) {

            DB::rollBack();

            throw $e;

        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error(
                'Error al actualizar usuario',
                [
                    'user_id' =>
                        $id,

                    'company_id' =>
                        $companyId,

                    'error' =>
                        $e->getMessage(),

                    'line' =>
                        $e->getLine(),

                    'file' =>
                        $e->getFile()
                ]
            );

            return response()->json([

                'success' => false,

                'message' =>
                    $e->getMessage()

            ], 500);
        }
    }
}