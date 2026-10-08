<?php

namespace App\Domains\Contabilidad\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Contabilidad\Models\AccountingAccountType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\QueryException;

class AccountingAccountTypeController extends Controller
{
    /**
     * Listar tipos de cuentas contables.
     */
    public function index(Request $request)
    {
        try {
            $query = AccountingAccountType::query();

            /*
            |--------------------------------------------------------------------------
            | Búsqueda
            |--------------------------------------------------------------------------
            */

            if ($request->filled('search')) {
                $search = trim($request->search);

                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('nature', 'like', "%{$search}%");
                });
            }

            /*
            |--------------------------------------------------------------------------
            | Estado
            |--------------------------------------------------------------------------
            */

            if ($request->filled('status')) {
                $status = filter_var(
                    $request->status,
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE
                );

                if ($status !== null) {
                    $query->where('status', $status);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Orden
            |--------------------------------------------------------------------------
            */

            $types = $query
                ->orderBy('id')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Tipos de cuentas obtenidos correctamente.',
                'data' => $types,
            ], 200);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los tipos de cuentas.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mostrar un tipo de cuenta.
     */
    public function show($id)
    {
        try {

            $accountType = AccountingAccountType::find($id);

            if (!$accountType) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tipo de cuenta no encontrado.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Tipo de cuenta obtenido correctamente.',
                'data' => $accountType,
            ], 200);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el tipo de cuenta.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Crear un tipo de cuenta.
     */
    public function store(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'code' => [
                    'required',
                    'string',
                    'max:20',
                    'unique:accounting_account_types,code',
                ],

                'name' => [
                    'required',
                    'string',
                    'max:100',
                ],

                'nature' => [
                    'required',
                    'in:DEUDORA,ACREEDORA',
                ],

                'status' => [
                    'nullable',
                    'boolean',
                ],

                'description' => [
                    'nullable',
                    'string',
                    'max:500',
                ],
            ],
            [
                'code.required' => 'El código es obligatorio.',
                'code.unique' => 'El código ya existe.',
                'name.required' => 'El nombre es obligatorio.',
                'nature.required' => 'La naturaleza es obligatoria.',
                'nature.in' => 'La naturaleza debe ser DEUDORA o ACREEDORA.',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {

            $accountType = AccountingAccountType::create([
                'code' => strtoupper(trim($request->code)),
                'name' => trim($request->name),
                'nature' => strtoupper($request->nature),
                'status' => $request->has('status')
                    ? (bool) $request->status
                    : true,
                'description' => $request->description
                    ? trim($request->description)
                    : null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tipo de cuenta creado correctamente.',
                'data' => $accountType,
            ], 201);

        } catch (QueryException $e) {

            return response()->json([
                'success' => false,
                'message' => 'No se pudo crear el tipo de cuenta.',
                'error' => $e->getMessage(),
            ], 500);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Error al crear el tipo de cuenta.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar un tipo de cuenta.
     */
    public function update(Request $request, $id)
    {
        $accountType = AccountingAccountType::find($id);

        if (!$accountType) {
            return response()->json([
                'success' => false,
                'message' => 'Tipo de cuenta no encontrado.',
            ], 404);
        }

        $validator = Validator::make(
            $request->all(),
            [
                'code' => [
                    'required',
                    'string',
                    'max:20',
                    'unique:accounting_account_types,code,' . $id,
                ],

                'name' => [
                    'required',
                    'string',
                    'max:100',
                ],

                'nature' => [
                    'required',
                    'in:DEUDORA,ACREEDORA',
                ],

                'status' => [
                    'nullable',
                    'boolean',
                ],

                'description' => [
                    'nullable',
                    'string',
                    'max:500',
                ],
            ],
            [
                'code.required' => 'El código es obligatorio.',
                'code.unique' => 'El código ya existe.',
                'name.required' => 'El nombre es obligatorio.',
                'nature.required' => 'La naturaleza es obligatoria.',
                'nature.in' => 'La naturaleza debe ser DEUDORA o ACREEDORA.',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {

            $accountType->update([
                'code' => strtoupper(trim($request->code)),
                'name' => trim($request->name),
                'nature' => strtoupper($request->nature),
                'status' => $request->has('status')
                    ? (bool) $request->status
                    : $accountType->status,
                'description' => $request->description
                    ? trim($request->description)
                    : null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tipo de cuenta actualizado correctamente.',
                'data' => $accountType->fresh(),
            ], 200);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el tipo de cuenta.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Activar / desactivar un tipo de cuenta.
     */
    public function toggleStatus($id)
    {
        try {

            $accountType = AccountingAccountType::find($id);

            if (!$accountType) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tipo de cuenta no encontrado.',
                ], 404);
            }

            $accountType->status = !$accountType->status;
            $accountType->save();

            return response()->json([
                'success' => true,
                'message' => $accountType->status
                    ? 'Tipo de cuenta activado correctamente.'
                    : 'Tipo de cuenta desactivado correctamente.',
                'data' => $accountType,
            ], 200);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar el estado.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Eliminar un tipo de cuenta.
     */
    public function destroy($id)
    {
        try {

            $accountType = AccountingAccountType::find($id);

            if (!$accountType) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tipo de cuenta no encontrado.',
                ], 404);
            }

            /*
            |--------------------------------------------------------------------------
            | No permitir eliminar si tiene cuentas asociadas
            |--------------------------------------------------------------------------
            */

            if (
                method_exists($accountType, 'accounts') &&
                $accountType->accounts()->exists()
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar este tipo porque tiene cuentas contables asociadas.',
                ], 409);
            }

            $accountType->delete();

            return response()->json([
                'success' => true,
                'message' => 'Tipo de cuenta eliminado correctamente.',
            ], 200);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el tipo de cuenta.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}