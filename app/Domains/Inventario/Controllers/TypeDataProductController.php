<?php

namespace App\Domains\Inventario\Controllers;

use App\Domains\Inventario\Models\Product;
use App\Domains\Inventario\Models\TypeData;
use App\Domains\Inventario\Models\TypeDataProduct;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;


class TypeDataProductController extends Controller
{


    public function index(Request $request)
    {
        $user = $request->user();

        $data = TypeDataProduct::with([
            'product',
            'typeData'
        ])
        ->where(
            'id_company',
            $user->id_company
        )
        ->orderBy('id', 'desc')
        ->get();

        return response()->json([
            'message' => 'Relaciones obtenidas correctamente',
            'data' => $data
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    | Asociar un tipo de dato a un producto
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | Validación
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([

            'product_id' => [
                'required',
                'integer',
                'exists:products,id'
            ],

            'type_data_id' => [
                'required',
                'integer',
                'exists:type_data,id'
            ],

            'status' => [
                'nullable',
                'boolean'
            ]

        ]);


        /*
        |--------------------------------------------------------------------------
        | Verificar producto de la empresa
        |--------------------------------------------------------------------------
        */

        $product = Product::where(
            'id',
            $validated['product_id']
        )
        ->where(
            'id_company',
            $user->id_company
        )
        ->first();

        if (!$product) {

            return response()->json([
                'message' => 'El producto no pertenece a la empresa autenticada.'
            ], 422);

        }


        /*
        |--------------------------------------------------------------------------
        | Verificar TypeData de la empresa
        |--------------------------------------------------------------------------
        */

        $typeData = TypeData::where(
            'id',
            $validated['type_data_id']
        )
        ->where(
            'id_company',
            $user->id_company
        )
        ->first();

        if (!$typeData) {

            return response()->json([
                'message' => 'El tipo de dato no pertenece a la empresa autenticada.'
            ], 422);

        }


        /*
        |--------------------------------------------------------------------------
        | Verificar que no exista la relación
        |--------------------------------------------------------------------------
        */

        $exists = TypeDataProduct::where(
            'id_company',
            $user->id_company
        )
        ->where(
            'product_id',
            $validated['product_id']
        )
        ->where(
            'type_data_id',
            $validated['type_data_id']
        )
        ->exists();

        if ($exists) {

            return response()->json([
                'message' => 'Este tipo de dato ya está asociado al producto.'
            ], 422);

        }


        /*
        |--------------------------------------------------------------------------
        | Crear relación
        |--------------------------------------------------------------------------
        */

        $relation = TypeDataProduct::create([

            'id_company' => $user->id_company,

            'product_id' => $validated['product_id'],

            'type_data_id' => $validated['type_data_id'],

            'status' => $validated['status'] ?? true

        ]);


        /*
        |--------------------------------------------------------------------------
        | Respuesta
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'message' => 'Tipo de dato asociado correctamente al producto.',
            'data' => $relation->load([
                'product',
                'typeData'
            ])
        ], 201);
    }


    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */

    public function show(Request $request, $id)
    {
        $user = $request->user();

        $relation = TypeDataProduct::with([
            'product',
            'typeData'
        ])
        ->where('id', $id)
        ->where(
            'id_company',
            $user->id_company
        )
        ->first();

        if (!$relation) {

            return response()->json([
                'message' => 'Relación no encontrada.'
            ], 404);

        }

        return response()->json([
            'message' => 'Relación obtenida correctamente',
            'data' => $relation
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    | Actualizar estado de la relación
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, $id)
    {
        $user = $request->user();

        $relation = TypeDataProduct::where(
            'id',
            $id
        )
        ->where(
            'id_company',
            $user->id_company
        )
        ->first();

        if (!$relation) {

            return response()->json([
                'message' => 'Relación no encontrada.'
            ], 404);

        }


        $validated = $request->validate([

            'status' => [
                'required',
                'boolean'
            ]

        ]);


        $relation->update([
            'status' => $validated['status']
        ]);


        return response()->json([
            'message' => 'Relación actualizada correctamente',
            'data' => $relation->fresh()->load([
                'product',
                'typeData'
            ])
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | DESTROY
    |--------------------------------------------------------------------------
    */

    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        $relation = TypeDataProduct::where(
            'id',
            $id
        )
        ->where(
            'id_company',
            $user->id_company
        )
        ->first();

        if (!$relation) {

            return response()->json([
                'message' => 'Relación no encontrada.'
            ], 404);

        }


        $relation->delete();


        return response()->json([
            'message' => 'Relación eliminada correctamente'
        ]);
    }
}
