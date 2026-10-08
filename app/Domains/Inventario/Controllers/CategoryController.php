<?php

namespace App\Domains\Inventario\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

use App\Domains\Inventario\Models\Category;
use App\Domains\Inventario\Models\Product;
use App\Domains\Seguridad\Models\Access;

class CategoryController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    | Listar categorías de la empresa autenticada
    |--------------------------------------------------------------------------
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

            $categories = Category::where(
                'id_company',
                $user->id_company
            )
                ->orderBy('category_name')
                ->get();

            $access = Access::where('user_id', $user->id)
                ->where('module_id', 16) //modulo 16 catalogo
                ->first();

            return response()->json([
                'success' => true,
                'data' => ['data' => $categories, 'access' => $access],
                //'access'=> $access
            ]);
        } catch (\Throwable $e) {

            Log::error('Error obteniendo categorías', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudieron obtener las categorías.'
            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    | Registrar categoría
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        try {

            /*
            |--------------------------------------------------------------------------
            | USUARIO AUTENTICADO
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
            | VALIDACIÓN
            |--------------------------------------------------------------------------
            |
            | Ya NO necesitamos recibir code_category.
            |
            | Laravel lo genera automáticamente.
            |
            */

            $validated = $request->validate([

                'category_name' => [
                    'required',
                    'string',
                    'max:100'
                ],

                'status' => [
                    'nullable',
                    'boolean'
                ],

            ]);


            /*
            |--------------------------------------------------------------------------
            | OBTENER ÚLTIMA CATEGORÍA
            |--------------------------------------------------------------------------
            */

            $lastCategory = Category::where(
                'id_company',
                $user->id_company
            )
                ->orderByDesc('id')
                ->first();


            /*
            |--------------------------------------------------------------------------
            | CALCULAR SIGUIENTE NÚMERO
            |--------------------------------------------------------------------------
            */

            if (!$lastCategory) {

                $nextNumber = 1;
            } else {

                $lastNumber = (int) str_replace(
                    'CAT-',
                    '',
                    $lastCategory->code_category
                );

                $nextNumber = $lastNumber + 1;
            }


            /*
            |--------------------------------------------------------------------------
            | GENERAR CÓDIGO
            |--------------------------------------------------------------------------
            */

            do {

                $codeCategory = 'CAT-' . str_pad(
                    $nextNumber,
                    5,
                    '0',
                    STR_PAD_LEFT
                );

                $exists = Category::where(
                    'id_company',
                    $user->id_company
                )
                    ->where(
                        'code_category',
                        $codeCategory
                    )
                    ->exists();

                $nextNumber++;
            } while ($exists);


            /*
            |--------------------------------------------------------------------------
            | CREAR CATEGORÍA
            |--------------------------------------------------------------------------
            */

            $category = Category::create([

                'code_category' => $codeCategory,

                'id_company' => $user->id_company,

                'category_name' => trim(
                    $validated['category_name']
                ),

                'status' => $request->has('status')
                    ? $request->boolean('status')
                    : true,

            ]);


            /*
            |--------------------------------------------------------------------------
            | RESPUESTA
            |--------------------------------------------------------------------------
            */

            return response()->json([

                'success' => true,

                'message' =>
                'Categoría registrada correctamente.',

                'data' => $category

            ], 201);
        } catch (\Throwable $e) {

            Log::error('Error creando categoría', [

                'error' => $e->getMessage(),

                'line' => $e->getLine(),

                'file' => $e->getFile(),

            ]);

            return response()->json([

                'success' => false,

                'message' =>
                'No se pudo registrar la categoría.'

            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    | Obtener categoría por ID
    |--------------------------------------------------------------------------
    */

    public function show(Request $request, int $id)
    {
        try {

            $user = $request->user();

            if (!$user) {

                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado.'
                ], 401);
            }


            /*
            |--------------------------------------------------------------------------
            | BUSCAR POR ID + EMPRESA
            |--------------------------------------------------------------------------
            */

            $category = Category::where(
                'id_company',
                $user->id_company
            )
                ->where(
                    'id',
                    $id
                )
                ->first();


            if (!$category) {

                return response()->json([
                    'success' => false,
                    'message' => 'Categoría no encontrada.'
                ], 404);
            }


            return response()->json([

                'success' => true,

                'data' => $category

            ], 200);
        } catch (\Throwable $e) {

            Log::error('Error obteniendo categoría', [

                'error' => $e->getMessage(),

                'line' => $e->getLine(),

                'file' => $e->getFile(),

            ]);

            return response()->json([

                'success' => false,

                'message' =>
                'No se pudo obtener la categoría.'

            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    | Actualizar categoría por ID
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, int $id)
    {
        try {

            /*
            |--------------------------------------------------------------------------
            | USUARIO
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
            | BUSCAR POR ID + EMPRESA
            |--------------------------------------------------------------------------
            |
            | IMPORTANTE:
            |
            | Aquí usamos:
            |
            | id = 5
            |
            | NO:
            |
            | CAT-00005
            |
            */

            $category = Category::where(
                'id_company',
                $user->id_company
            )
                ->where(
                    'id',
                    $id
                )
                ->first();


            if (!$category) {

                return response()->json([
                    'success' => false,
                    'message' => 'Categoría no encontrada.'
                ], 404);
            }


            /*
            |--------------------------------------------------------------------------
            | VALIDACIÓN
            |--------------------------------------------------------------------------
            */

            $validated = $request->validate([

                'category_name' => [
                    'required',
                    'string',
                    'max:100'
                ],

                'status' => [
                    'nullable',
                    'boolean'
                ],

            ]);


            /*
            |--------------------------------------------------------------------------
            | ACTUALIZAR
            |--------------------------------------------------------------------------
            |
            | NO modificamos:
            |
            | id
            | code_category
            | id_company
            |
            */

            $category->update([

                'category_name' =>
                trim($validated['category_name']),

                'status' =>
                $request->has('status')
                    ? $request->boolean('status')
                    : $category->status,

            ]);


            /*
            |--------------------------------------------------------------------------
            | RESPUESTA
            |--------------------------------------------------------------------------
            */

            return response()->json([

                'success' => true,

                'message' =>
                'Categoría actualizada correctamente.',

                'data' =>
                $category->fresh()

            ], 200);
        } catch (\Throwable $e) {

            Log::error('Error actualizando categoría', [

                'error' => $e->getMessage(),

                'line' => $e->getLine(),

                'file' => $e->getFile(),

            ]);

            return response()->json([

                'success' => false,

                'message' =>
                'No se pudo actualizar la categoría.'

            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | DESTROY
    |--------------------------------------------------------------------------
    | Eliminar categoría por ID
    |--------------------------------------------------------------------------
    */

    public function destroy(Request $request, int $id)
    {
        try {

            /*
            |--------------------------------------------------------------------------
            | USUARIO
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
            | BUSCAR POR ID + EMPRESA
            |--------------------------------------------------------------------------
            */

            $category = Category::where(
                'id_company',
                $user->id_company
            )
                ->where(
                    'id',
                    $id
                )
                ->first();


            if (!$category) {

                return response()->json([
                    'success' => false,
                    'message' => 'Categoría no encontrada.'
                ], 404);
            }


            /*
            |--------------------------------------------------------------------------
            | VERIFICAR PRODUCTOS RELACIONADOS
            |--------------------------------------------------------------------------
            */

            if ($category->products()->exists()) {

                return response()->json([

                    'success' => false,

                    'message' =>
                    'No se puede eliminar la categoría porque tiene productos asociados.',

                    'category' => [

                        'id' =>
                        $category->id,

                        'code_category' =>
                        $category->code_category,

                        'category_name' =>
                        $category->category_name

                    ]

                ], 409);
            }


            /*
            |--------------------------------------------------------------------------
            | ELIMINAR
            |--------------------------------------------------------------------------
            */

            $category->delete();


            /*
            |--------------------------------------------------------------------------
            | RESPUESTA
            |--------------------------------------------------------------------------
            */

            return response()->json([

                'success' => true,

                'message' =>
                'Categoría eliminada correctamente.'

            ], 200);
        } catch (\Throwable $e) {

            Log::error('Error eliminando categoría', [

                'error' => $e->getMessage(),

                'line' => $e->getLine(),

                'file' => $e->getFile(),

            ]);

            return response()->json([

                'success' => false,

                'message' =>
                'No se pudo eliminar la categoría.'

            ], 500);
        }
    }
}
