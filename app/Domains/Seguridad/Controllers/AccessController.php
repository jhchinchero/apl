<?php

namespace App\Domains\Seguridad\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Domains\Seguridad\Models\User;
use App\Domains\Seguridad\Models\Access;
use App\Domains\Seguridad\Models\Module;

class AccessController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | LISTAR USUARIOS + ACCESOS +
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        try {

            $authUser = $request->user();

            if (!$authUser) {

                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado.'
                ], 401);
            }

            $companyId = $authUser->id_company;

            if (!$companyId) {

                return response()->json([
                    'success' => false,
                    'message' => 'El usuario no pertenece a ninguna empresa.'
                ], 400);
            }

            $users = User::with([
                'role',
                'branches',
                'accesses.module'
            ])
            ->where(
                'id_company',
                $companyId
            )
            ->get();

            return response()->json([
                'success' => true,
                'message' => 'Usuarios obtenidos correctamente.',
                'data' => $users
            ]);

        } catch (\Throwable $e) {

            Log::error(
                'Error obteniendo accesos',
                [
                    'error' => $e->getMessage()
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los accesos.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | LISTAR MODULOS PARA LOS USUARIOS CLIENTES
    |--------------------------------------------------------------------------
    */

   public function modules(Request $request)
{
    try {

        $authUser = $request->user();

        if (!$authUser) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.'
            ], 401);
        }

        $modules = Module::where('status', true)
            ->where('id', '!=', 25)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Módulos obtenidos correctamente.',
            'data' => $modules
        ]);

    } catch (\Throwable $e) {

        Log::error($e);

        return response()->json([
            'success' => false,
            'message' => 'No se pudieron obtener los módulos.',
            'error' => $e->getMessage()
        ], 500);
    }
}


    /*
    |--------------------------------------------------------------------------
    | ACTUALIZAR ACCESOS
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, $userId)
    {
        try {

            $authUser = $request->user();

            if (!$authUser) {

                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado.'
                ], 401);
            }


            /*
            |--------------------------------------------------------------------------
            | BUSCAR USUARIO DENTRO DE LA EMPRESA
            |--------------------------------------------------------------------------
            */

            $user = User::where(
                'id',
                $userId
            )
            ->where(
                'id_company',
                $authUser->id_company
            )
            ->first();

            if (!$user) {

                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado.'
                ], 404);
            }


            /*
            |--------------------------------------------------------------------------
            | VALIDACION
            |--------------------------------------------------------------------------
            */

            $validated = $request->validate([

                'accesses' => [
                    'nullable',
                    'array'
                ],

                'accesses.*.module_id' => [
                    'required',
                    'integer',
                    'exists:modules,id'
                ],

                'accesses.*.can_view' => [
                    'nullable',
                    'boolean'
                ],

                'accesses.*.can_create' => [
                    'nullable',
                    'boolean'
                ],

                'accesses.*.can_edit' => [
                    'nullable',
                    'boolean'
                ],

                'accesses.*.can_delete' => [
                    'nullable',
                    'boolean'
                ],

            ]);


            /*
            |--------------------------------------------------------------------------
            | GUARDAR
            |--------------------------------------------------------------------------
            */

            DB::transaction(function () use (
                $user,
                $validated
            ) {

                /*
                |--------------------------------------------------------------------------
                | ELIMINAR ACCESOS ANTERIORES
                |--------------------------------------------------------------------------
                */

                Access::where(
                    'user_id',
                    $user->id
                )->delete();


                /*
                |--------------------------------------------------------------------------
                | INSERTAR NUEVOS ACCESOS
                |--------------------------------------------------------------------------
                */

                foreach (
                    $validated['accesses'] ?? []
                    as $access
                ) {

                    Access::create([

                        'user_id' =>
                            $user->id,

                        'module_id' =>
                            $access['module_id'],

                        'can_view' =>
                            $access['can_view'] ?? false,

                        'can_create' =>
                            $access['can_create'] ?? false,

                        'can_edit' =>
                            $access['can_edit'] ?? false,

                        'can_delete' =>
                            $access['can_delete'] ?? false,

                    ]);
                }

            });


            return response()->json([

                'success' => true,

                'message' =>
                    'Accesos actualizados correctamente.'

            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {

            return response()->json([

                'success' => false,

                'message' =>
                    'Error de validación.',

                'errors' =>
                    $e->errors()

            ], 422);

        } catch (\Throwable $e) {

            Log::error(
                'Error actualizando accesos',
                [
                    'user_id' =>
                        $userId,

                    'company_id' =>
                        $request->user()?->id_company,

                    'request' =>
                        $request->all(),

                    'error' =>
                        $e->getMessage(),

                    'file' =>
                        $e->getFile(),

                    'line' =>
                        $e->getLine(),
                ]
            );

            return response()->json([

                'success' => false,

                'message' =>
                    'No se pudieron actualizar los accesos.',

                'error' =>
                    $e->getMessage()

            ], 500);
        }
    }
}