<?php

namespace App\Domains\Inventario\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Domains\Inventario\Models\Brand;
use App\Domains\Inventario\Models\Product;
use App\Domains\Seguridad\Models\Access;


class BrandController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    | Listar marcas de la empresa autenticada
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

            $brands = Brand::where(
                'id_company',
                $user->id_company
            )
            ->orderBy('brand_name')
            ->get();
             $access = Access::where('user_id', $user->id)
                ->where('module_id', 16) //modulo 16 catalogo
                ->first();

            return response()->json([
                'success' => true,
                'data' => ['data'=>$brands, 'access'=>$access]
            ]);

        } catch (\Throwable $e) {

            Log::error('Error obteniendo marcas', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudieron obtener las marcas.'
            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    | Crear marca
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
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
            | VALIDACIÓN
            |--------------------------------------------------------------------------
            */

            $validated = $request->validate([

                'brand_name' => [
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
            | GENERAR CODE_BRAND
            |--------------------------------------------------------------------------
            |
            | Ejemplo:
            |
            | BRA-00001
            | BRA-00002
            | BRA-00003
            |
            | La secuencia es independiente por empresa.
            |
            */

            $lastBrand = Brand::where(
                'id_company',
                $user->id_company
            )
            ->orderByDesc('id')
            ->first();


            if (!$lastBrand) {

                $nextNumber = 1;

            } else {

                $lastNumber = (int) str_replace(
                    'BRA-',
                    '',
                    $lastBrand->code_brand
                );

                $nextNumber = $lastNumber + 1;
            }


            /*
            |--------------------------------------------------------------------------
            | EVITAR DUPLICADOS
            |--------------------------------------------------------------------------
            */

            do {

                $codeBrand = 'BRA-' . str_pad(
                    $nextNumber,
                    5,
                    '0',
                    STR_PAD_LEFT
                );

                $exists = Brand::where(
                    'id_company',
                    $user->id_company
                )
                ->where(
                    'code_brand',
                    $codeBrand
                )
                ->exists();

                $nextNumber++;

            } while ($exists);


            /*
            |--------------------------------------------------------------------------
            | CREAR MARCA
            |--------------------------------------------------------------------------
            */

            $brand = Brand::create([

                'code_brand' => $codeBrand,

                'id_company' => $user->id_company,

                'brand_name' => trim(
                    $validated['brand_name']
                ),

                'status' => $request->has('status')
                    ? $request->boolean('status')
                    : true,

            ]);


            return response()->json([

                'success' => true,

                'message' => 'Marca registrada correctamente.',

                'data' => $brand

            ], 201);

        } catch (\Throwable $e) {

            Log::error('Error creando marca', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo registrar la marca.'
            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    | Obtener una marca por ID
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
            | BUSCAR POR ID
            |--------------------------------------------------------------------------
            |
            | El ID que viene en la URL es:
            |
            | /brands/2
            |
            */

            $brand = Brand::where(
                'id_company',
                $user->id_company
            )
            ->where(
                'id',
                $id
            )
            ->first();


            if (!$brand) {

                return response()->json([
                    'success' => false,
                    'message' => 'Marca no encontrada.'
                ], 404);
            }


            return response()->json([
                'success' => true,
                'data' => $brand
            ]);

        } catch (\Throwable $e) {

            Log::error('Error obteniendo marca', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo obtener la marca.'
            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    | Actualizar marca por ID
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, int $id)
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
            |
            | Ejemplo:
            |
            | PUT /api/brands/2
            |
            | id = 2
            |
            */

            $brand = Brand::where(
                'id_company',
                $user->id_company
            )
            ->where(
                'id',
                $id
            )
            ->first();


            if (!$brand) {

                return response()->json([
                    'success' => false,
                    'message' => 'Marca no encontrada.'
                ], 404);
            }


            /*
            |--------------------------------------------------------------------------
            | VALIDACIÓN
            |--------------------------------------------------------------------------
            */

            $validated = $request->validate([

                'brand_name' => [
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
            | code_brand
            | id_company
            |
            */

            $brand->update([

                'brand_name' => trim(
                    $validated['brand_name']
                ),

                'status' => $request->has('status')
                    ? $request->boolean('status')
                    : $brand->status,

            ]);


            return response()->json([

                'success' => true,

                'message' => 'Marca actualizada correctamente.',

                'data' => $brand->fresh()

            ], 200);

        } catch (\Throwable $e) {

            Log::error('Error actualizando marca', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo actualizar la marca.'
            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | DESTROY
    |--------------------------------------------------------------------------
    | Eliminar marca por ID
    |--------------------------------------------------------------------------
    */

    public function destroy(Request $request, int $id)
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
            |
            | Ejemplo:
            |
            | DELETE /api/brands/2
            |
            */

            $brand = Brand::where(
                'id_company',
                $user->id_company
            )
            ->where(
                'id',
                $id
            )
            ->first();


            if (!$brand) {

                return response()->json([
                    'success' => false,
                    'message' => 'Marca no encontrada.'
                ], 404);
            }


            /*
            |--------------------------------------------------------------------------
            | VERIFICAR PRODUCTOS RELACIONADOS
            |--------------------------------------------------------------------------
            */

            if ($brand->products()->exists()) {

                return response()->json([
                    'success' => false,
                    'message' =>
                        'No se puede eliminar la marca porque tiene productos asociados.'
                ], 409);
            }


            /*
            |--------------------------------------------------------------------------
            | ELIMINAR
            |--------------------------------------------------------------------------
            */

            $brand->delete();


            return response()->json([
                'success' => true,
                'message' => 'Marca eliminada correctamente.'
            ], 200);

        } catch (\Throwable $e) {

            Log::error('Error eliminando marca', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo eliminar la marca.'
            ], 500);
        }
    }
}
