<?php

namespace App\Domains\Inventario\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Domains\Inventario\Models\TypeData;
use App\Domains\Inventario\Models\TypeDataProduct;


use App\Domains\Seguridad\Models\Access;

use Illuminate\Validation\Rule;

class TypeDataController extends Controller
{
    //
    /**
     * Listar tipos de datos de la empresa.
     */
    public function index(Request $request)
    {
        try {

            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado.'
                ], 401);
            }

            $types = TypeData::where(
                'id_company',
                $user->id_company
            )
                ->orderBy('type_name')
                ->get();
            $access = Access::where('user_id', $user->id)
                ->where('module_id', 16) //modulo 16 catalogo
                ->first();

            return response()->json([
                'success' => true,
                'data' => ['data' => $types, 'access' => $access]
            ]);
        } catch (\Throwable $e) {

            Log::error($e);

            return response()->json([
                'success' => false,
                'message' => 'No se pudieron obtener los tipos de datos.'
            ], 500);
        }
    }


    /**
     * Registrar tipo de dato.
     */
    public function store(Request $request)
    {
        try {

            /*
            |--------------------------------------------------------------------------
            | Usuario autenticado
            |--------------------------------------------------------------------------
            */

            $user = $request->user();

            if (!$user) {

                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado.'
                ], 401);
            }

            /*
            |--------------------------------------------------------------------------
            | Validación
            |--------------------------------------------------------------------------
            */

            $request->validate([

                'id_type_data' => [
                    'nullable',
                    'string',
                    'max:50',

                    Rule::unique('type_data', 'id_type_data')
                        ->where(function ($query) use ($user) {

                            return $query->where(
                                'id_company',
                                $user->id_company
                            );
                        }),
                ],

                'type_name' => [
                    'required',
                    'string',
                    'max:100',
                ],

                'code' => [
                    'nullable',
                    'string',
                    'max:100',
                ],

                'status' => [
                    'nullable',
                    'boolean',
                ],
            ]);

             $access = Access::where('user_id', $user->id)
                ->where('module_id', 16) // Catálogo
                ->first();

            if (!$access || !$access->can_create) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tiene permisos para editar en Catálogo.'
                ], 403);
            }

            /*
            |--------------------------------------------------------------------------
            | Generar ID
            |--------------------------------------------------------------------------
            */

            if ($request->filled('id_type_data')) {

                /*
                |--------------------------------------------------------------------------
                | Vue envió un código manual
                |--------------------------------------------------------------------------
                */

                $idTypeData = trim(
                    $request->id_type_data
                );
            } else {

                /*
                |--------------------------------------------------------------------------
                | Generar código automáticamente
                |--------------------------------------------------------------------------
                */

                $count = TypeData::where(
                    'id_company',
                    $user->id_company
                )->count() + 1;

                $idTypeData = 'TD-' . str_pad(
                    $count,
                    5,
                    '0',
                    STR_PAD_LEFT
                );

                /*
                |--------------------------------------------------------------------------
                | Evitar códigos duplicados
                |--------------------------------------------------------------------------
                */

                while (
                    TypeData::where(
                        'id_company',
                        $user->id_company
                    )
                    ->where(
                        'id_type_data',
                        $idTypeData
                    )
                    ->exists()
                ) {

                    $count++;

                    $idTypeData = 'TD-' . str_pad(
                        $count,
                        5,
                        '0',
                        STR_PAD_LEFT
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Crear tipo de dato
            |--------------------------------------------------------------------------
            */

            $type = TypeData::create([

                'id_type_data' => $idTypeData,

                'id_company' => $user->id_company,

                'status' => $request->has('status')
                    ? $request->boolean('status')
                    : true,

                'type_name' => trim(
                    $request->type_name
                ),

                'code' => $request->filled('code')
                    ? trim($request->code)
                    : null,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Respuesta
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'success' => true,
                'message' => 'Tipo de dato registrado correctamente.',
                'data' => $type
            ], 201);
        } catch (\Throwable $e) {

            /*
            |--------------------------------------------------------------------------
            | Registrar error
            |--------------------------------------------------------------------------
            */

            Log::error('Error creando tipo de dato', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo registrar el tipo de dato.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Mostrar un tipo de dato.
     */
    public function show(Request $request, $id)
    {
        try {

            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado.'
                ], 401);
            }


            $type = TypeData::where(
                'id_company',
                $user->id_company
            )
                ->where('id_type_data', $id)
                ->firstOrFail();


            return response()->json([
                'success' => true,
                'data' => $type
            ]);
        } catch (\Throwable $e) {

            Log::error($e);

            return response()->json([
                'success' => false,
                'message' => 'Tipo de dato no encontrado.'
            ], 404);
        }
    }


    /**
     * Actualizar tipo de dato.
     */
    public function update(Request $request, $id)
    {
        try {

            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado.'
                ], 401);
            }




            $request->validate([
                'type_name' => 'required|string|max:100',
                'code' => 'nullable|string|max:255',
                'status' => 'required|boolean'
            ]);

            $access = Access::where('user_id', $user->id)
                ->where('module_id', 16) // Catálogo
                ->first();

            if (!$access || !$access->can_edit) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tiene permisos para editar en Catálogo.'
                ], 403);
            }


            $type = TypeData::where(
                'id_company',
                $user->id_company
            )
                ->where('id_type_data', $id)
                ->firstOrFail();


            $type->update([

                'type_name' => $request->type_name,

                'code' => $request->code,

                'status' => $request->status

            ]);


            return response()->json([
                'success' => true,
                'message' => 'Tipo de dato actualizado correctamente.',
                'data' => $type
            ]);
        } catch (\Throwable $e) {

            Log::error($e);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo actualizar el tipo de dato.'
            ], 500);
        }
    }


    /**
     * Eliminar tipo de dato.
     */

    public function destroy(Request $request, $id)
    {
        try {

            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado.'
                ], 401);
            }

             $access = Access::where('user_id', $user->id)
                ->where('module_id', 16) // Catálogo
                ->first();

            if (!$access || !$access->can_delete) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tiene permisos para editar en Catálogo.'
                ], 403);
            }

            // Buscar el tipo de dato perteneciente a la empresa
            $type = TypeData::where(
                'id_company',
                $user->id_company
            )
                ->where(
                    'id_type_data',
                    $id
                )
                ->first();

            if (!$type) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tipo de dato no encontrado.'
                ], 404);
            }

            /*
        |--------------------------------------------------------------------------
        | Verificar si está relacionado con algún producto
        |--------------------------------------------------------------------------
        */

            $usado = TypeDataProduct::where(
                'type_data_id',
                $type->id
            )->exists();

            if ($usado) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar este tipo de dato porque está relacionado con uno o más productos.'
                ], 409);
            }

            /*
        |--------------------------------------------------------------------------
        | Eliminar
        |--------------------------------------------------------------------------
        */

            $type->delete();

            return response()->json([
                'success' => true,
                'message' => 'Tipo de dato eliminado correctamente.'
            ], 200);
        } catch (\Throwable $e) {

            Log::error('Error eliminando tipo de dato', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo eliminar el tipo de dato.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
