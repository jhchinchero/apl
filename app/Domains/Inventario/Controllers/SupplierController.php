<?php

namespace App\Domains\Inventario\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use App\Domains\Inventario\Models\Supplier;

use App\Domains\Seguridad\Models\Access;
class SupplierController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX
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

            $suppliers = Supplier::where(
                'id_company',
                $user->id_company
            )
            ->orderBy('name')
            ->get();

             $access = Access::where('user_id', $user->id)
                ->where('module_id', 16) //modulo 16 catalogo
                ->first();

            return response()->json([
                'success' => true,
                'data' => ['data'=>$suppliers, 'access'=>$access]
            ]);

        } catch (\Throwable $e) {

            Log::error('Error obteniendo proveedores', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudieron obtener los proveedores.'
            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | STORE
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

            $validated = $request->validate([

                'name' => [
                    'required',
                    'string',
                    'max:100'
                ],

                'email' => [
                    'nullable',
                    'email',
                    'max:255'
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

                'reference' => [
                    'nullable',
                    'string',
                    'max:255'
                ],

                'status' => [
                    'nullable',
                    'boolean'
                ],
            ]);


            /*
            |--------------------------------------------------------------------------
            | Generar código SUP-00001
            |--------------------------------------------------------------------------
            */

            $lastSupplier = Supplier::where(
                'id_company',
                $user->id_company
            )
            ->orderByDesc('id')
            ->first();

            if (!$lastSupplier) {

                $nextNumber = 1;

            } else {

                $lastNumber = (int) str_replace(
                    'SUP-',
                    '',
                    $lastSupplier->code_supplier
                );

                $nextNumber = $lastNumber + 1;
            }


            do {

                $codeSupplier = 'SUP-' . str_pad(
                    $nextNumber,
                    5,
                    '0',
                    STR_PAD_LEFT
                );

                $exists = Supplier::where(
                    'id_company',
                    $user->id_company
                )
                ->where(
                    'code_supplier',
                    $codeSupplier
                )
                ->exists();

                $nextNumber++;

            } while ($exists);


            /*
            |--------------------------------------------------------------------------
            | Crear proveedor
            |--------------------------------------------------------------------------
            */

            $supplier = Supplier::create([

                'code_supplier' => $codeSupplier,

                'id_company' => $user->id_company,

                'name' => trim($validated['name']),

                'email' => !empty($validated['email'])
                    ? trim($validated['email'])
                    : null,

                'phone' => !empty($validated['phone'])
                    ? trim($validated['phone'])
                    : null,

                'address' => !empty($validated['address'])
                    ? trim($validated['address'])
                    : null,

                'reference' => !empty($validated['reference'])
                    ? trim($validated['reference'])
                    : null,

                'status' => $request->has('status')
                    ? $request->boolean('status')
                    : true,
            ]);


            return response()->json([
                'success' => true,
                'message' => 'Proveedor registrado correctamente.',
                'data' => $supplier
            ], 201);

        } catch (\Throwable $e) {

            Log::error('Error creando proveedor', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo registrar el proveedor.'
            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | SHOW
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


            $supplier = Supplier::where(
                'id_company',
                $user->id_company
            )
            ->where('id', $id)
            ->first();


            if (!$supplier) {
                return response()->json([
                    'success' => false,
                    'message' => 'Proveedor no encontrado.'
                ], 404);
            }


            return response()->json([
                'success' => true,
                'data' => $supplier
            ]);

        } catch (\Throwable $e) {

            Log::error('Error obteniendo proveedor', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo obtener el proveedor.'
            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE
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
            | Buscar por ID numérico
            |--------------------------------------------------------------------------
            */

            $supplier = Supplier::where(
                'id_company',
                $user->id_company
            )
            ->where('id', $id)
            ->first();


            if (!$supplier) {
                return response()->json([
                    'success' => false,
                    'message' => 'Proveedor no encontrado.'
                ], 404);
            }


            /*
            |--------------------------------------------------------------------------
            | Validación
            |--------------------------------------------------------------------------
            */

            $validated = $request->validate([

                'name' => [
                    'required',
                    'string',
                    'max:100'
                ],

                'email' => [
                    'nullable',
                    'email',
                    'max:255'
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

                'reference' => [
                    'nullable',
                    'string',
                    'max:255'
                ],

                'status' => [
                    'nullable',
                    'boolean'
                ],
            ]);


            /*
            |--------------------------------------------------------------------------
            | Actualizar
            |--------------------------------------------------------------------------
            |
            | NO modificamos:
            |
            | id
            | code_supplier
            | id_company
            |
            */

            $supplier->update([

                'name' => trim($validated['name']),

                'email' => !empty($validated['email'])
                    ? trim($validated['email'])
                    : null,

                'phone' => !empty($validated['phone'])
                    ? trim($validated['phone'])
                    : null,

                'address' => !empty($validated['address'])
                    ? trim($validated['address'])
                    : null,

                'reference' => !empty($validated['reference'])
                    ? trim($validated['reference'])
                    : null,

                'status' => $request->has('status')
                    ? $request->boolean('status')
                    : $supplier->status,
            ]);


            return response()->json([
                'success' => true,
                'message' => 'Proveedor actualizado correctamente.',
                'data' => $supplier->fresh()
            ]);

        } catch (\Throwable $e) {

            Log::error('Error actualizando proveedor', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo actualizar el proveedor.'
            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | DESTROY
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
            | Buscar por ID numérico
            |--------------------------------------------------------------------------
            */

            $supplier = Supplier::where(
                'id_company',
                $user->id_company
            )
            ->where('id', $id)
            ->first();


            if (!$supplier) {
                return response()->json([
                    'success' => false,
                    'message' => 'Proveedor no encontrado.'
                ], 404);
            }


            /*
            |--------------------------------------------------------------------------
            | Verificar productos relacionados
            |--------------------------------------------------------------------------
            */

            if ($supplier->products()->exists()) {

                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar el proveedor porque tiene productos asociados.'
                ], 409);
            }


            /*
            |--------------------------------------------------------------------------
            | Eliminar
            |--------------------------------------------------------------------------
            */

            $supplier->delete();


            return response()->json([
                'success' => true,
                'message' => 'Proveedor eliminado correctamente.'
            ]);

        } catch (\Throwable $e) {

            Log::error('Error eliminando proveedor', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo eliminar el proveedor.'
            ], 500);
        }
    }
}