<?php

namespace App\Domains\Caja\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Caja\Models\CashRegister;
use App\Domains\Seguridad\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Domains\Seguridad\Models\Access; 

class CashRegisterAssignmentController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | LISTAR CAJAS Y USUARIOS
    |--------------------------------------------------------------------------
    */

     public function index()
    {
        $authUser = Auth::user();

        $companyId = $authUser->id_company;

        /*
        |--------------------------------------------------------------------------
        | USUARIOS DE LA EMPRESA
        |--------------------------------------------------------------------------
        */

        $users = User::where('id_company', $companyId)
            ->where('status', true)
            ->with([
                'branches',
                'role',

                'cashRegisters' => function ($query) use ($companyId) {

                    $query
                        ->wherePivot('id_company', $companyId)
                        ->wherePivot('status', true)
                        ->with([
                            'branch',
                            'pointOfSale',
                            'openSession',
                        ]);
                }
            ])
            ->orderBy('name')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | CAJAS DE LA EMPRESA
        |--------------------------------------------------------------------------
        */

        $cashRegisters = CashRegister::where(
                'id_company',
                $companyId
            )
            ->where('status', true)
            ->with([

                /*
                |--------------------------------------------------------------------------
                | SUCURSAL
                |--------------------------------------------------------------------------
                */

                'branch',

                /*
                |--------------------------------------------------------------------------
                | PUNTO DE VENTA
                |--------------------------------------------------------------------------
                */

                'pointOfSale',

                /*
                |--------------------------------------------------------------------------
                | SESIÓN ABIERTA
                |--------------------------------------------------------------------------
                */

                'openSession',

                /*
                |--------------------------------------------------------------------------
                | USUARIOS ASIGNADOS
                |--------------------------------------------------------------------------
                */

                'users' => function ($query) use ($companyId) {

                    $query
                        ->wherePivot(
                            'id_company',
                            $companyId
                        )
                        ->wherePivot(
                            'status',
                            true
                        )
                        ->with([
                            'role'
                        ])
                        ->orderBy('name');
                },
            ])
            ->orderBy('name')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | FORMATEAR CAJAS
        |--------------------------------------------------------------------------
        */

        $cashRegisters = $cashRegisters->map(
            function ($cashRegister) {

                /*
                |--------------------------------------------------------------------------
                | SESIÓN ABIERTA
                |--------------------------------------------------------------------------
                */

                $openSession = $cashRegister->openSession;

                return [

                    /*
                    |--------------------------------------------------------------------------
                    | DATOS DE LA CAJA
                    |--------------------------------------------------------------------------
                    */

                    'id' => $cashRegister->id,

                    'id_company' => $cashRegister->id_company,

                    'code' => $cashRegister->code,

                    'name' => $cashRegister->name,

                    'description' => $cashRegister->description,

                    'status' => (bool) $cashRegister->status,


                    /*
                    |--------------------------------------------------------------------------
                    | SUCURSAL
                    |--------------------------------------------------------------------------
                    */

                    'branch_id' => $cashRegister->branch_id,

                    'branch_name' =>
                        $cashRegister->branch?->branch_name
                        ?? $cashRegister->branch?->name
                        ?? 'Sin sucursal',


                    /*
                    |--------------------------------------------------------------------------
                    | PUNTO DE VENTA
                    |--------------------------------------------------------------------------
                    */

                    'point_of_sale_id' =>
                        $cashRegister->point_of_sale_id,

                    'pos_name' =>
                        $cashRegister->pointOfSale?->name
                        ?? 'Sin POS',


                    /*
                    |--------------------------------------------------------------------------
                    | USUARIOS ASIGNADOS
                    |--------------------------------------------------------------------------
                    */

                    'users' => $cashRegister->users
                        ->map(function ($user) {

                            return [

                                'id' => $user->id,

                                'name' => $user->name,

                                'email' => $user->email,

                                'role' => $user->role?->name
                                    ?? 'Sin rol',

                            ];

                        })
                        ->values(),


                    /*
                    |--------------------------------------------------------------------------
                    | ESTADO DE LA CAJA
                    |--------------------------------------------------------------------------
                    |
                    | 1 = tiene sesión abierta
                    | 0 = no tiene sesión abierta
                    |
                    */

                    'open_sessions' =>
                        $openSession ? 1 : 0,


                    /*
                    |--------------------------------------------------------------------------
                    | INFORMACIÓN DE LA SESIÓN ABIERTA
                    |--------------------------------------------------------------------------
                    */

                    'open_session' => $openSession
                        ? [

                            'id' =>
                                $openSession->id,

                            'user_id' =>
                                $openSession->user_id,

                            'user_name' =>
                                $openSession->user?->name
                                ?? 'Usuario',

                            'opening_amount' =>
                                $openSession->opening_amount,

                            'expected_amount' =>
                                $openSession->expected_amount,

                            'opened_at' =>
                                $openSession->opened_at,

                            'status' =>
                                $openSession->status,

                        ]
                        : null,
                ];
            }
        );

        /*
        |--------------------------------------------------------------------------
        | RESPUESTA
        |--------------------------------------------------------------------------
        */
        $access = Access::where('user_id', $authUser->id)
                ->where('module_id', 19) //modulo 16 catalogo
                ->first();

        return response()->json([

            'success' => true,

            'data' => [

                /*
                |--------------------------------------------------------------------------
                | USUARIOS
                |--------------------------------------------------------------------------
                */

                'users' => $users,

                /*
                |--------------------------------------------------------------------------
                | CAJAS
                |--------------------------------------------------------------------------
                */

                'cash_registers' => $cashRegisters,
                'access'=>$access

            ]

        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | ASIGNAR VARIOS USUARIOS
    |--------------------------------------------------------------------------
    */

    public function assign(Request $request)
    {
        $authUser = Auth::user();

        $companyId = $authUser->id_company;

        /*
        |--------------------------------------------------------------------------
        | VALIDACIÓN
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([
            'cash_register_id' => [
                'required',
                'integer'
            ],

            'user_ids' => [
                'required',
                'array'
            ],

            'user_ids.*' => [
                'required',
                'integer'
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | CAJA
        |--------------------------------------------------------------------------
        */

        $cashRegister = CashRegister::where('id', $validated['cash_register_id'])
            ->where('id_company', $companyId)
            ->first();

        if (!$cashRegister) {

            return response()->json([
                'success' => false,
                'message' => 'La caja no existe o no pertenece a la empresa.'
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | USUARIOS
        |--------------------------------------------------------------------------
        */

        $targetUsers = User::whereIn(
            'id',
            $validated['user_ids']
        )
            ->where('id_company', $companyId)
            ->where('status', true)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | VALIDAR TODOS LOS USUARIOS
        |--------------------------------------------------------------------------
        */

        if (
            $targetUsers->count() !==
            count($validated['user_ids'])
        ) {

            return response()->json([
                'success' => false,
                'message' => 'Uno o más usuarios no pertenecen a la empresa o están inactivos.'
            ], 422);
        }

        DB::beginTransaction();

        try {

            /*
            |--------------------------------------------------------------------------
            | VERIFICAR SI ALGÚN USUARIO TIENE OTRA CAJA
            |--------------------------------------------------------------------------
            */

            foreach ($targetUsers as $targetUser) {

                $otherAssignment = DB::table('cash_register_user')
                    ->where('id_company', $companyId)
                    ->where('user_id', $targetUser->id)
                    ->where('status', true)
                    ->where(
                        'cash_register_id',
                        '!=',
                        $cashRegister->id
                    )
                    ->exists();

                if ($otherAssignment) {

                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' =>
                        "El usuario {$targetUser->name} ya tiene otra caja asignada."
                    ], 422);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | DESACTIVAR ASIGNACIONES QUE YA NO ESTÁN SELECCIONADAS
            |--------------------------------------------------------------------------
            */

            DB::table('cash_register_user')
                ->where('id_company', $companyId)
                ->where('cash_register_id', $cashRegister->id)
                ->whereNotIn(
                    'user_id',
                    $validated['user_ids']
                )
                ->update([
                    'status' => false,
                    'updated_at' => now()
                ]);

            /*
            |--------------------------------------------------------------------------
            | INSERTAR / REACTIVAR
            |--------------------------------------------------------------------------
            */

            foreach ($targetUsers as $targetUser) {

                $assignment = DB::table('cash_register_user')
                    ->where('id_company', $companyId)
                    ->where('cash_register_id', $cashRegister->id)
                    ->where('user_id', $targetUser->id)
                    ->first();

                if ($assignment) {

                    DB::table('cash_register_user')
                        ->where('id', $assignment->id)
                        ->update([
                            'status' => true,
                            'updated_at' => now()
                        ]);
                } else {

                    DB::table('cash_register_user')
                        ->insert([
                            'id_company' => $companyId,

                            'cash_register_id' =>
                            $cashRegister->id,

                            'user_id' =>
                            $targetUser->id,

                            'status' => true,

                            'created_at' => now(),

                            'updated_at' => now(),
                        ]);
                }
            }

            DB::commit();

            /*
            |--------------------------------------------------------------------------
            | RESPUESTA
            |--------------------------------------------------------------------------
            */

            $cashRegister->load([
                'branch',
                'pointOfSale',
                'users'
            ]);

            return response()->json([
                'success' => true,

                'message' =>
                'Usuarios asignados correctamente.',

                'data' => [
                    'cash_register' => $cashRegister
                ]
            ]);
        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,

                'message' =>
                'No se pudieron actualizar las asignaciones.',

                'error' => $e->getMessage()

            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | QUITAR USUARIO
    |--------------------------------------------------------------------------
    */

    public function remove(Request $request)
    {
        $authUser = Auth::user();

        $companyId = $authUser->id_company;

        $validated = $request->validate([
            'user_id' => [
                'required',
                'integer'
            ],

            'cash_register_id' => [
                'required',
                'integer'
            ],
        ]);

        $updated = DB::table('cash_register_user')
            ->where('id_company', $companyId)
            ->where(
                'cash_register_id',
                $validated['cash_register_id']
            )
            ->where(
                'user_id',
                $validated['user_id']
            )
            ->where('status', true)
            ->update([
                'status' => false,
                'updated_at' => now()
            ]);

        if (!$updated) {

            return response()->json([
                'success' => false,
                'message' =>
                'La asignación no existe o ya está desactivada.'
            ], 404);
        }

        return response()->json([
            'success' => true,

            'message' =>
            'Usuario retirado de la caja correctamente.'
        ]);
    }
}
