<?php

namespace App\Domains\Facturacion\Controllers\Homologacion;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Domains\Inventario\Models\Product;

class HomologacionProductoController extends Controller
{
    /**
     * Guardar / actualizar homologación SIAT de un producto.
     */
    public function guardar(Request $request)
    {
        $validated = $request->validate([
            'product_id' => [
                'required',
                'integer',
                'exists:products,id',
            ],

            'codigo_actividad' => [
                'required',
                'integer',
            ],

            'codigo_producto_siat' => [
                'required',
                'integer',
            ],

            'codigo_unidad_medida' => [
                'required',
                'integer',
            ],
        ]);

        try {

            $user = Auth::user();

            /*
             * Buscar el producto y verificar que
             * pertenezca a la empresa del usuario.
             */
            $producto = Product::query()
                ->where('id', $validated['product_id'])
                ->where('id_company', $user->id_company)
                ->first();

            if (!$producto) {
                return response()->json([
                    'success' => false,
                    'message' => 'El producto no pertenece a la empresa del usuario.'
                ], 404);
            }

            DB::beginTransaction();

            $producto->update([
                'actividad_codigo' => $validated['codigo_actividad'],
                'codigo_producto_sin' => $validated['codigo_producto_siat'],
                'unit_of_measure' => $validated['codigo_unidad_medida'],
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Producto homologado correctamente.',
                'data' => [
                    'id' => $producto->id,
                    'code_product' => $producto->code_product,
                    'actividad_codigo' => $producto->actividad_codigo,
                    'codigo_producto_sin' => $producto->codigo_producto_sin,
                    'unit_of_measure' => $producto->unit_of_measure,
                ]
            ], 200);

        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'No se pudo guardar la homologación.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}