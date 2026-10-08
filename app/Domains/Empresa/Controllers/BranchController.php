<?php

namespace App\Domains\Empresa\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

use App\Domains\Empresa\Models\Branch;
use App\Domains\Seguridad\Models\Access; 
use App\Domains\Empresa\Models\PointOfSale;


class BranchController extends Controller
{

 /**
     * ============================================================
     * SUCURSALES + PUNTOS DE VENTA ASIGNADOS AL USUARIO
     * ============================================================
     *
     * Solo devuelve:
     *
     * - Sucursales asignadas al usuario.
     * - Puntos de venta asignados al usuario dentro de cada sucursal.
     *
     * NO devuelve:
     *
     * - Sucursales que no pertenecen al usuario.
     * - Puntos de venta que no fueron asignados al usuario.
     */
    public function getBranchUserAccess()
    {
        try {

            $user = Auth::user();

            /*
            |--------------------------------------------------------------------------
            | USUARIO AUTENTICADO
            |--------------------------------------------------------------------------
            */

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado.'
                ], 401);
            }

            /*
            |--------------------------------------------------------------------------
            | EMPRESA
            |--------------------------------------------------------------------------
            */

            if (!$user->id_company) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario no tiene una empresa asignada.'
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | SUCURSALES ASIGNADAS AL USUARIO
            |--------------------------------------------------------------------------
            |
            | user_branches
            |
            | user_id
            | branch_id
            | point_of_sale_id
            |
            */

            $branches = Branch::where('id_company', $user->id_company)
                ->whereHas('users', function ($query) use ($user) {

                    $query->where('users.id', $user->id);

                })
                ->orderBy('id')
                ->get();

            /*
            |--------------------------------------------------------------------------
            | ARMAR RESPUESTA
            |--------------------------------------------------------------------------
            */

            $data = $branches->map(function ($branch) use ($user) {

                /*
                |--------------------------------------------------------------------------
                | OBTENER LOS POS ASIGNADOS AL USUARIO EN ESTA SUCURSAL
                |--------------------------------------------------------------------------
                */

                $pointOfSaleIds = $branch->users()
                    ->where('users.id', $user->id)
                    ->pluck('user_branches.point_of_sale_id')
                    ->filter()
                    ->unique()
                    ->values();

                /*
                |--------------------------------------------------------------------------
                | OBTENER SOLAMENTE LOS POS ASIGNADOS
                |--------------------------------------------------------------------------
                */

                $pointOfSales = PointOfSale::where('id_company', $user->id_company)
                    ->where('branch_id', $branch->id)
                    ->whereIn('id', $pointOfSaleIds)
                    ->orderBy('id')
                    ->get();

                /*
                |--------------------------------------------------------------------------
                | DEVOLVER SUCURSAL
                |--------------------------------------------------------------------------
                */

                return [
                    'id' => $branch->id,

                    'id_company' => $branch->id_company,

                    'code_branch' => $branch->code_branch,

                    'codigo' => $branch->codigo,

                    'branch_name' => $branch->branch_name,

                    'department' => $branch->department,

                    'municipality' => $branch->municipality,

                    'address' => $branch->address,

                    'contact_name' => $branch->contact_name,

                    'phone' => $branch->phone,

                    'status' => $branch->status,

                    /*
                    |--------------------------------------------------------------------------
                    | PUNTOS DE VENTA ASIGNADOS
                    |--------------------------------------------------------------------------
                    */

                    'point_of_sales' => $pointOfSales->map(function ($pointOfSale) {

                        return [
                            'id' => $pointOfSale->id,

                            'id_company' => $pointOfSale->id_company,

                            'branch_id' => $pointOfSale->branch_id,

                            'codigo' => $pointOfSale->codigo,

                            'name' => $pointOfSale->name,

                            'status' => $pointOfSale->status,
                        ];

                    })->values(),
                ];
            })->values();

            /*
            |--------------------------------------------------------------------------
            | RESPUESTA
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'success' => true,
                'message' => 'Sucursales y puntos de venta asignados obtenidos correctamente.',
                'data' => $data
            ]);

        } catch (\Throwable $e) {

            Log::error(
                'Error al cargar sucursales y puntos de venta asignados al usuario',
                [
                    'user_id' => Auth::id(),

                    'company_id' => Auth::user()->id_company ?? null,

                    'error' => $e->getMessage(),

                    'file' => $e->getFile(),

                    'line' => $e->getLine(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'No se pudieron cargar las sucursales y puntos de venta.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | LISTAR SUCURSALES + PUNTOS DE VENTA
    |--------------------------------------------------------------------------
    */
    public function getBranchPointOfSale(Request $request)
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
            | SUCURSALES DE LA EMPRESA
            |--------------------------------------------------------------------------
            |
            | Sucursal
            |    └── Puntos de venta
            |
            */

            $branches = Branch::where(
                    'id_company',
                    $user->id_company
                )
                ->with([
                    'pointOfSales' => function ($query) {

                        $query
                            ->select([
                                'id',
                                'id_company',
                                'branch_id',
                                'codigo',
                                'name',
                                'status'
                            ])
                            ->where('status', true)
                            ->orderBy('id');

                    }
                ])
                ->orderBy('id')
                ->get();

            /*
            |--------------------------------------------------------------------------
            | RESPUESTA
            |--------------------------------------------------------------------------
            */

            return response()->json([

                'success' => true,

                'data' => $branches

            ]);

        } catch (\Throwable $e) {

            Log::error(
                'Error al cargar sucursales y puntos de venta',
                [

                    'user_id' =>
                        $request->user()?->id,

                    'id_company' =>
                        $request->user()?->id_company,

                    'error' =>
                        $e->getMessage(),

                    'file' =>
                        $e->getFile(),

                    'line' =>
                        $e->getLine()

                ]
            );

            return response()->json([

                'success' => false,

                'message' =>
                    'No se pudieron cargar las sucursales.',

                'error' =>
                    $e->getMessage()

            ], 500);
        }
    }
    /*
    |--------------------------------------------------------------------------
    | CREAR SUCURSAL
    |--------------------------------------------------------------------------
    |
    | code_branch:
    | BRN-00001
    | BRN-00002
    | BRN-00003
    |
    | codigo:
    | 0
    | 1
    | 2
    | 3
    |
    | Ambos se generan por empresa.
    |
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

                'branch_name' =>
                    'required|string|max:100',

                'department' =>
                    'nullable|string|max:100',

                'municipality' =>
                    'nullable|string|max:100',

                'address' =>
                    'nullable|string|max:255',

                'contact_name' =>
                    'nullable|string|max:100',

                'phone' =>
                    'nullable|string|max:50',

                'status' =>
                    'nullable|boolean',

            ]);


            /*
            |--------------------------------------------------------------------------
            | INICIAR TRANSACCIÓN
            |--------------------------------------------------------------------------
            */

            DB::beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | GENERAR CODE_BRANCH
            |--------------------------------------------------------------------------
            |
            | Ejemplo:
            |
            | BRN-00001
            | BRN-00002
            | BRN-00003
            |
            | La numeración es independiente por empresa.
            |
            */

            $lastBranch = Branch::where(
                'id_company',
                $user->id_company
            )
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();


            if ($lastBranch) {

                $lastNumber = (int) str_replace(
                    'BRN-',
                    '',
                    $lastBranch->code_branch
                );

                $number = $lastNumber + 1;

            } else {

                $number = 1;
            }


            /*
            |--------------------------------------------------------------------------
            | FORMAR CODE_BRANCH
            |--------------------------------------------------------------------------
            */

            $codeBranch = 'BRN-' . str_pad(
                $number,
                5,
                '0',
                STR_PAD_LEFT
            );


            /*
            |--------------------------------------------------------------------------
            | GENERAR CODIGO
            |--------------------------------------------------------------------------
            |
            | Primera sucursal:
            | codigo = 0
            |
            | Segunda:
            | codigo = 1
            |
            | Tercera:
            | codigo = 2
            |
            */

            $lastCodigo = Branch::where(
                'id_company',
                $user->id_company
            )
            ->max('codigo');


            if ($lastCodigo === null) {

                $codigo = 0;

            } else {

                $codigo = (int) $lastCodigo + 1;
            }


            /*
            |--------------------------------------------------------------------------
            | CREAR SUCURSAL
            |--------------------------------------------------------------------------
            */

            $branch = Branch::create([

                'id_company' =>
                    $user->id_company,

                'code_branch' =>
                    $codeBranch,

                'codigo' =>
                    $codigo,

                'branch_name' =>
                    trim($validated['branch_name']),

                'department' =>
                    isset($validated['department'])
                        ? trim($validated['department'])
                        : null,

                'municipality' =>
                    isset($validated['municipality'])
                        ? trim($validated['municipality'])
                        : null,

                'address' =>
                    isset($validated['address'])
                        ? trim($validated['address'])
                        : null,

                'contact_name' =>
                    isset($validated['contact_name'])
                        ? trim($validated['contact_name'])
                        : null,

                'phone' =>
                    isset($validated['phone'])
                        ? trim($validated['phone'])
                        : null,

                'status' =>
                    $request->has('status')
                        ? $request->boolean('status')
                        : true,

            ]);


            /*
            |--------------------------------------------------------------------------
            | CONFIRMAR
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
                    'Sucursal registrada correctamente.',

                'data' =>
                    $branch

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
            | REGISTRAR ERROR
            |--------------------------------------------------------------------------
            */

            Log::error(
                'Error al registrar sucursal',
                [
                    'user_id' =>
                        $request->user()?->id,

                    'id_company' =>
                        $request->user()?->id_company,

                    'error' =>
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
                    'No se pudo registrar la sucursal.',

            ], 500);
        }
    }

    
    /*
    |--------------------------------------------------------------------------
    | LISTAR
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

            $branches = Branch::where(
                    'id_company',
                    $user->id_company
                )
                ->orderBy('id')
                ->get();
            $access = Access::where('user_id', $user->id)
                ->where('module_id', 18) //modulo 18 sucursales
                ->first();

            return response()->json([
                'success' => true,
                'data' => ['data'=>$branches, 'access'=>$access]
            ]);

        } catch (\Throwable $e) {

            Log::error($e);

            return response()->json([
                'success' => false,
                'message' => 'No se pudieron cargar las sucursales.'
            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | MOSTRAR
    |--------------------------------------------------------------------------
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

            $branch = Branch::where(
                    'id_company',
                    $user->id_company
                )
                ->where('id', $id)
                ->with('pointOfSales')
                ->first();

            if (!$branch) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sucursal no encontrada.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $branch
            ]);

        } catch (\Throwable $e) {

            Log::error($e);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo obtener la sucursal.'
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ACTUALIZAR
    |--------------------------------------------------------------------------
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
                'branch_name' => 'required|string|max:100',
                'department' => 'nullable|string|max:100',
                'municipality' => 'nullable|string|max:100',
                'address' => 'nullable|string|max:255',
                'contact_name' => 'nullable|string|max:100',
                'phone' => 'nullable|string|max:50',
                'status' => 'nullable|boolean',
            ]);

            $branch = Branch::where(
                    'id_company',
                    $user->id_company
                )
                ->where('id', $id)
                ->first();

            if (!$branch) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sucursal no encontrada.'
                ], 404);
            }

            $branch->update([

                'branch_name' =>
                    trim($validated['branch_name']),

                'department' =>
                    $validated['department'] ?? null,

                'municipality' =>
                    $validated['municipality'] ?? null,

                'address' =>
                    $validated['address'] ?? null,

                'contact_name' =>
                    $validated['contact_name'] ?? null,

                'phone' =>
                    $validated['phone'] ?? null,

                'status' =>
                    $request->has('status')
                        ? $request->boolean('status')
                        : $branch->status,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Sucursal actualizada correctamente.',
                'data' => $branch->fresh()
            ]);

        } catch (\Throwable $e) {

            Log::error($e);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo actualizar la sucursal.'
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ELIMINAR
    |--------------------------------------------------------------------------
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

            $branch = Branch::where(
                    'id_company',
                    $user->id_company
                )
                ->where('id', $id)
                ->first();

            if (!$branch) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sucursal no encontrada.'
                ], 404);
            }

            if ($branch->pointOfSales()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar la sucursal porque tiene puntos de venta asociados.'
                ], 409);
            }

            if ($branch->users()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar la sucursal porque tiene usuarios asociados.'
                ], 409);
            }

            if ($branch->stocks()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar la sucursal porque tiene stock asociado.'
                ], 409);
            }

            $branch->delete();

            return response()->json([
                'success' => true,
                'message' => 'Sucursal eliminada correctamente.'
            ]);

        } catch (\Throwable $e) {

            Log::error($e);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo eliminar la sucursal.'
            ], 500);
        }
    }
}