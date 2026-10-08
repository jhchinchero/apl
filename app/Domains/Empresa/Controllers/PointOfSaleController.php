<?php

namespace App\Domains\Empresa\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Domains\Empresa\Models\PointOfSale;
use App\Domains\Empresa\Models\Branch;

class PointOfSaleController extends Controller
{
        /**
     * ============================================================
     * CREAR POS
     * ============================================================
     */
    /**
     * ============================================================
     * CREAR POS
     * ============================================================
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
            | VALIDAR DATOS
            |--------------------------------------------------------------------------
            */

            $validated = $request->validate([

                'branch_id' => [
                    'required',
                    'integer'
                ],

                'name' => [
                    'required',
                    'string',
                    'max:150'
                ],

                'status' => [
                    'nullable',
                    'boolean'
                ],

            ]);


            /*
            |--------------------------------------------------------------------------
            | VERIFICAR SUCURSAL
            |--------------------------------------------------------------------------
            |
            | La sucursal debe pertenecer a la empresa
            | del usuario autenticado.
            |
            */

            $branch = Branch::where(
                'id_company',
                $user->id_company
            )
            ->where(
                'id',
                $validated['branch_id']
            )
            ->first();


            if (!$branch) {

                return response()->json([
                    'success' => false,
                    'message' => 'La sucursal no pertenece a la empresa.'
                ], 404);
            }


            /*
            |--------------------------------------------------------------------------
            | INICIAR TRANSACCIÓN
            |--------------------------------------------------------------------------
            */

            DB::beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | OBTENER ÚLTIMO CÓDIGO DE ESTA SUCURSAL
            |--------------------------------------------------------------------------
            |
            | IMPORTANTE:
            |
            | Se filtra por:
            |
            | id_company
            | branch_id
            |
            | De esta manera cada sucursal tiene su propia numeración.
            |
            */

            $lastCodigo = PointOfSale::where(
                'id_company',
                $user->id_company
            )
            ->where(
                'branch_id',
                $branch->id
            )
            ->max('codigo');


            /*
            |--------------------------------------------------------------------------
            | GENERAR CODIGO
            |--------------------------------------------------------------------------
            |
            | Primera caja de la sucursal:
            | 0
            |
            | Segunda:
            | 1
            |
            | Tercera:
            | 2
            |
            | Cuarta:
            | 3
            |
            */

            if ($lastCodigo === null) {

                $codigo = 0;

            } else {

                $codigo = (int) $lastCodigo + 1;
            }


            /*
            |--------------------------------------------------------------------------
            | CREAR POS
            |--------------------------------------------------------------------------
            */

            $point = PointOfSale::create([

                'id_company' =>
                    $user->id_company,

                'branch_id' =>
                    $branch->id,

                'codigo' =>
                    $codigo,

                'name' =>
                    trim($validated['name']),

                'status' =>
                    $request->has('status')
                        ? $request->boolean('status')
                        : true,

            ]);


            /*
            |--------------------------------------------------------------------------
            | CARGAR RELACIÓN
            |--------------------------------------------------------------------------
            */

            $point->load('branch');


            /*
            |--------------------------------------------------------------------------
            | CONFIRMAR TRANSACCIÓN
            |--------------------------------------------------------------------------
            */

            DB::commit();


            /*
            |--------------------------------------------------------------------------
            | RESPUESTA
            |--------------------------------------------------------------------------
            */

            return response()->json([

                'success' => true,

                'message' =>
                    'Punto de venta registrado correctamente.',

                'data' =>
                    $point

            ], 201);


        } catch (\Throwable $e) {

            /*
            |--------------------------------------------------------------------------
            | ROLLBACK
            |--------------------------------------------------------------------------
            */

            DB::rollBack();


            /*
            |--------------------------------------------------------------------------
            | LOG
            |--------------------------------------------------------------------------
            */

            Log::error(
                'ERROR CREANDO POS',
                [

                    'user_id' =>
                        $request->user()?->id,

                    'id_company' =>
                        $request->user()?->id_company,

                    'message' =>
                        $e->getMessage(),

                    'file' =>
                        $e->getFile(),

                    'line' =>
                        $e->getLine(),

                ]
            );


            /*
            |--------------------------------------------------------------------------
            | RESPUESTA DE ERROR
            |--------------------------------------------------------------------------
            */

            return response()->json([

                'success' => false,

                'message' =>
                    'No se pudo registrar el punto de venta.'

            ], 500);
        }
    }
    /**
     * ============================================================
     * LISTAR POS DE UNA SUCURSAL
     * ============================================================
     */
    public function getByBranch(Request $request, $branchId)
    {
        try {

            // --------------------------------------------------------
            // USUARIO
            // --------------------------------------------------------

            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado.'
                ], 401);
            }

            // --------------------------------------------------------
            // VALIDAR BRANCH ID
            // --------------------------------------------------------

            if (!is_numeric($branchId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'El ID de sucursal no es válido.'
                ], 422);
            }

            $branchId = (int) $branchId;

            // --------------------------------------------------------
            // BUSCAR SUCURSAL
            // --------------------------------------------------------

            $branch = Branch::where('id_company', $user->id_company)
                ->where('id', $branchId)
                ->first();

            if (!$branch) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sucursal no encontrada.'
                ], 404);
            }

            // --------------------------------------------------------
            // OBTENER POS
            // --------------------------------------------------------

            $points = PointOfSale::where(
                    'id_company',
                    $user->id_company
                )
                ->where(
                    'branch_id',
                    $branch->id
                )
                ->with('branch')
                ->orderBy('id')
                ->get();

            // --------------------------------------------------------
            // RESPUESTA
            // --------------------------------------------------------

            return response()->json([
                'success' => true,
                'message' => 'Puntos de venta cargados correctamente.',
                'data' => $points
            ], 200);

        } catch (\Throwable $e) {

            // --------------------------------------------------------
            // LOG DETALLADO
            // --------------------------------------------------------

            Log::error('ERROR AL CARGAR PUNTOS DE VENTA', [
                'branch_id' => $branchId ?? null,
                'user_id' => $request->user()?->id,
                'id_company' => $request->user()?->id_company,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            // --------------------------------------------------------
            // RESPUESTA
            // --------------------------------------------------------

            return response()->json([
                'success' => false,
                'message' => 'No se pudieron cargar los puntos de venta.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ============================================================
     * MOSTRAR POS
     * ============================================================
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

            $point = PointOfSale::where(
                    'id_company',
                    $user->id_company
                )
                ->where('id', $id)
                ->with('branch')
                ->first();

            if (!$point) {
                return response()->json([
                    'success' => false,
                    'message' => 'Punto de venta no encontrado.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $point
            ], 200);

        } catch (\Throwable $e) {

            Log::error('ERROR MOSTRANDO POS', [
                'id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo obtener el punto de venta.',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    /**
     * ============================================================
     * ACTUALIZAR POS
     * ============================================================
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

            $validated = $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:150'
                ],

                'status' => [
                    'nullable',
                    'boolean'
                ],
            ]);

            $point = PointOfSale::where(
                    'id_company',
                    $user->id_company
                )
                ->where('id', $id)
                ->first();

            if (!$point) {
                return response()->json([
                    'success' => false,
                    'message' => 'Punto de venta no encontrado.'
                ], 404);
            }

            $point->update([

                'name' => trim(
                    $validated['name']
                ),

                'status' => $request->has('status')
                    ? $request->boolean('status')
                    : $point->status,
            ]);

            $point->load('branch');

            return response()->json([
                'success' => true,
                'message' => 'Punto de venta actualizado correctamente.',
                'data' => $point
            ], 200);

        } catch (\Throwable $e) {

            Log::error('ERROR ACTUALIZANDO POS', [
                'id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo actualizar el punto de venta.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ============================================================
     * ELIMINAR POS
     * ============================================================
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

            $point = PointOfSale::where(
                    'id_company',
                    $user->id_company
                )
                ->where('id', $id)
                ->first();

            if (!$point) {
                return response()->json([
                    'success' => false,
                    'message' => 'Punto de venta no encontrado.'
                ], 404);
            }

            $point->delete();

            return response()->json([
                'success' => true,
                'message' => 'Punto de venta eliminado correctamente.'
            ], 200);

        } catch (\Throwable $e) {

            Log::error('ERROR ELIMINANDO POS', [
                'id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo eliminar el punto de venta.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}