<?php

namespace App\Domains\Seguridad\Controllers;

use App\Http\Controllers\Controller;

use App\Domains\Seguridad\Models\Access;
use App\Domains\Seguridad\Models\Module;
use App\Domains\Seguridad\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AccessModulesUserController extends Controller
{
    /**
     * ============================================================
     * OBTENER TODOS LOS MÓDULOS DISPONIBLES
     * ============================================================
     */
    /*  public function modules()
    {
        $authUser = Auth::user();

        if (!$authUser) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $modules = Module::where('status', true)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'description',
                'status',
            ]);

        return response()->json([
            'success' => true,
            'data' => $modules,
        ]);
    }  */
public function modules()
    {
        $authUser = Auth::user();

        if (!$authUser) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

  
        $query = Module::where('status', true);

       if (strtolower(trim($authUser->email)) !== 'disaat@disaat.com') {

    
           $query->where('id', '!=', 25);
        }

        $modules = $query
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'description',
                'status',
            ]);

        return response()->json([
            'success' => true,
            'data' => $modules,
        ]);
    }


    /**
     * ============================================================
     * OBTENER PERMISOS DE UN USUARIO
     * ============================================================
     *
     * El $userId viene de la URL.
     *
     * La empresa NO viene del frontend.
     * Se obtiene mediante Auth::user().
     *
     */
    public function userModules($userId)
    {
        $authUser = Auth::user();

        /**
         * --------------------------------------------------------
         * VERIFICAR AUTENTICACIÓN
         * --------------------------------------------------------
         */
        if (!$authUser) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }


        /**
         * --------------------------------------------------------
         * BUSCAR USUARIO DENTRO DE LA MISMA EMPRESA
         * --------------------------------------------------------
         */
        $user = User::where('id', $userId)
            ->where('id_company', $authUser->id_company)
            ->first();


        /**
         * --------------------------------------------------------
         * USUARIO NO ENCONTRADO
         * --------------------------------------------------------
         */
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no pertenece a su empresa.',
            ], 403);
        }


        /**
         * --------------------------------------------------------
         * OBTENER PERMISOS
         * --------------------------------------------------------
         */
        $accesses = Access::where('user_id', $user->id)
            ->get([
                'id',
                'user_id',
                'module_id',
                'can_view',
                'can_create',
                'can_edit',
                'can_delete',
            ]);


        /**
         * --------------------------------------------------------
         * RESPUESTA
         * --------------------------------------------------------
         */
        return response()->json([
            'success' => true,

            'data' => $accesses,

            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }


    /**
     * ============================================================
     * ASIGNAR / ACTUALIZAR PERMISOS DE UN USUARIO
     * ============================================================
     */
    public function assignModules(Request $request, $userId)
    {
        $authUser = Auth::user();


        /**
         * --------------------------------------------------------
         * VERIFICAR AUTENTICACIÓN
         * --------------------------------------------------------
         */
        if (!$authUser) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }


        /**
         * --------------------------------------------------------
         * VERIFICAR QUE EL USUARIO PERTENEZCA A LA EMPRESA
         * --------------------------------------------------------
         */
        $user = User::where('id', $userId)
            ->where('id_company', $authUser->id_company)
            ->first();


        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no pertenece a su empresa.',
            ], 403);
        }


        /**
         * --------------------------------------------------------
         * VALIDAR DATOS
         * --------------------------------------------------------
         */
        $validated = $request->validate([

            'modules' => [
                'nullable',
                'array',
            ],

            'modules.*.module_id' => [
                'required',
                'integer',
                'exists:modules,id',
            ],

            'modules.*.can_view' => [
                'required',
                'boolean',
            ],

            'modules.*.can_create' => [
                'required',
                'boolean',
            ],

            'modules.*.can_edit' => [
                'required',
                'boolean',
            ],

            'modules.*.can_delete' => [
                'required',
                'boolean',
            ],
        ]);


        /**
         * --------------------------------------------------------
         * GUARDAR PERMISOS
         * --------------------------------------------------------
         */
        DB::transaction(function () use ($validated, $user) {

            /**
             * Eliminar permisos anteriores
             */
            Access::where('user_id', $user->id)->delete();


            /**
             * Crear permisos nuevos
             */
            foreach ($validated['modules'] ?? [] as $module) {

                Access::create([
                    'id_company'=> $user->id_company,
                    'user_id' => $user->id,

                    'module_id' => $module['module_id'],

                    'can_view' => $module['can_view'],

                    'can_create' => $module['can_create'],

                    'can_edit' => $module['can_edit'],

                    'can_delete' => $module['can_delete'],
                ]);
            }
        });


        /**
         * --------------------------------------------------------
         * RESPUESTA
         * --------------------------------------------------------
         */
        return response()->json([
            'success' => true,

            'message' => 'Permisos actualizados correctamente.',

            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }
}
