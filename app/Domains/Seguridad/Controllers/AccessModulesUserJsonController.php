<?php

namespace App\Domains\Seguridad\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Seguridad\Models\Access;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;


class AccessModulesUserJsonController extends Controller
{
    /**
     * Obtener módulos y permisos
     * del usuario autenticado.
     */
    public function index()
    {
        try {

            /*
            |--------------------------------------------------------------------------
            | USUARIO AUTENTICADO
            |--------------------------------------------------------------------------
            */

            $user = Auth::user();

            if (!$user) {

                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado.'
                ], 401);
            }


            /*
            |--------------------------------------------------------------------------
            | ACCESOS
            |--------------------------------------------------------------------------
            */

            $accesses = Access::where('user_id', $user->id)
                ->where('can_view', 1)
                ->get();


            /*
            |--------------------------------------------------------------------------
            | FORMATEAR RESPUESTA
            |--------------------------------------------------------------------------
            */

            $data = $accesses->map(function ($access) {

                return [
                    'module' => $access->module_id,

                    'access' => [
                        'can_view'   => (bool) $access->can_view,
                        'can_create' => (bool) $access->can_create,
                        'can_edit'   => (bool) $access->can_edit,
                        'can_delete' => (bool) $access->can_delete,
                    ]
                ];

            })->values();


            /*
            |--------------------------------------------------------------------------
            | RESPUESTA
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'success' => true,
                'message' => 'Módulos y permisos obtenidos correctamente.',
                'data' => $data
            ]);

        } catch (\Throwable $e) {

            Log::error(
                'Error al obtener módulos del usuario autenticado',
                [
                    'user_id' => Auth::id(),
                    'error'   => $e->getMessage(),
                    'line'    => $e->getLine(),
                    'file'    => $e->getFile()
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los módulos.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}
