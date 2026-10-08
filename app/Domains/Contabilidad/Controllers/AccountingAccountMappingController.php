<?php

namespace App\Domains\Contabilidad\Controllers;


use App\Http\Controllers\Controller;

use App\Domains\Contabilidad\Models\AccountingAccountMapping;
use App\Domains\Contabilidad\Models\AccountingAccount;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AccountingAccountMappingController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | LISTAR
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $companyId = $user->id_company;

        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene empresa asignada.',
            ], 422);
        }

        $query = AccountingAccountMapping::query()
            ->where(
                'id_company',
                $companyId
            )
            ->with([
                'accountingAccount.accountType',
                'accountingAccount.parent',
            ])
            ->orderBy('name');

        /*
        |--------------------------------------------------------------------------
        | BUSCAR
        |--------------------------------------------------------------------------
        */

        if ($request->filled('search')) {

            $search = trim(
                $request->search
            );

            $query->where(function ($q) use ($search) {

                $q->where(
                    'key',
                    'LIKE',
                    "%{$search}%"
                )
                ->orWhere(
                    'name',
                    'LIKE',
                    "%{$search}%"
                )
                ->orWhere(
                    'description',
                    'LIKE',
                    "%{$search}%"
                );
            });
        }

        /*
        |--------------------------------------------------------------------------
        | STATUS
        |--------------------------------------------------------------------------
        */

        if ($request->has('status')) {

            $query->where(
                'status',
                filter_var(
                    $request->status,
                    FILTER_VALIDATE_BOOLEAN
                )
            );
        }

        /*
        |--------------------------------------------------------------------------
        | RESPUESTA
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,
            'message' => 'Mappings contables obtenidos correctamente.',
            'data' => $query->get(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | MOSTRAR
    |--------------------------------------------------------------------------
    */

    public function show(
        Request $request,
        $id
    ) {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $mapping = AccountingAccountMapping::query()
            ->where(
                'id_company',
                $user->id_company
            )
            ->with([
                'accountingAccount.accountType',
                'accountingAccount.parent',
            ])
            ->find($id);

        if (!$mapping) {

            return response()->json([
                'success' => false,
                'message' => 'Mapping contable no encontrado.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $mapping,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CREAR
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $validator = Validator::make(
            $request->all(),
            [
                'key' => [
                    'required',
                    'string',
                    'max:50',
                    'regex:/^[A-Z0-9_]+$/',
                ],

                'name' => [
                    'required',
                    'string',
                    'max:150',
                ],

                'accounting_account_id' => [
                    'required',
                    'integer',
                ],

                'description' => [
                    'nullable',
                    'string',
                ],

                'status' => [
                    'nullable',
                    'boolean',
                ],
            ],
            [
                'key.regex' =>
                    'La clave solo puede contener letras mayúsculas, números y guion bajo.',
            ]
        );

        if ($validator->fails()) {

            return response()->json([
                'success' => false,
                'message' => 'Los datos enviados no son válidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $companyId = $user->id_company;

        /*
        |--------------------------------------------------------------------------
        | VALIDAR KEY DUPLICADA
        |--------------------------------------------------------------------------
        */

        $exists = AccountingAccountMapping::query()
            ->where(
                'id_company',
                $companyId
            )
            ->where(
                'key',
                $request->key
            )
            ->exists();

        if ($exists) {

            return response()->json([
                'success' => false,
                'message' =>
                    'Ya existe un mapping con la clave ' .
                    $request->key .
                    ' para esta empresa.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | CUENTA
        |--------------------------------------------------------------------------
        */

        $account = AccountingAccount::query()
            ->where(
                'id',
                $request->accounting_account_id
            )
            ->where(
                'id_company',
                $companyId
            )
            ->where(
                'status',
                true
            )
            ->where(
                'allows_entries',
                true
            )
            ->with('accountType')
            ->first();

        if (!$account) {

            return response()->json([
                'success' => false,
                'message' =>
                    'La cuenta contable no existe, está inactiva, no pertenece a la empresa o no permite movimientos.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | CREAR
        |--------------------------------------------------------------------------
        */

        $mapping = AccountingAccountMapping::create([

            'id_company' =>
                $companyId,

            'key' =>
                strtoupper(
                    trim($request->key)
                ),

            'name' =>
                trim($request->name),

            'accounting_account_id' =>
                $account->id,

            'description' =>
                $request->description
                    ? trim($request->description)
                    : null,

            'status' =>
                $request->has('status')
                    ? (bool) $request->status
                    : true,
        ]);

        $mapping->load([
            'accountingAccount.accountType',
            'accountingAccount.parent',
        ]);

        return response()->json([
            'success' => true,
            'message' =>
                'Mapping contable creado correctamente.',
            'data' => $mapping,
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | ACTUALIZAR
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        $id
    ) {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $mapping = AccountingAccountMapping::query()
            ->where(
                'id_company',
                $user->id_company
            )
            ->find($id);

        if (!$mapping) {

            return response()->json([
                'success' => false,
                'message' => 'Mapping contable no encontrado.',
            ], 404);
        }

        $validator = Validator::make(
            $request->all(),
            [
                'key' => [
                    'required',
                    'string',
                    'max:50',
                    'regex:/^[A-Z0-9_]+$/',
                ],

                'name' => [
                    'required',
                    'string',
                    'max:150',
                ],

                'accounting_account_id' => [
                    'required',
                    'integer',
                ],

                'description' => [
                    'nullable',
                    'string',
                ],

                'status' => [
                    'required',
                    'boolean',
                ],
            ]
        );

        if ($validator->fails()) {

            return response()->json([
                'success' => false,
                'message' => 'Los datos enviados no son válidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | VALIDAR KEY
        |--------------------------------------------------------------------------
        */

        $exists = AccountingAccountMapping::query()
            ->where(
                'id_company',
                $user->id_company
            )
            ->where(
                'key',
                strtoupper(
                    trim($request->key)
                )
            )
            ->where(
                'id',
                '!=',
                $mapping->id
            )
            ->exists();

        if ($exists) {

            return response()->json([
                'success' => false,
                'message' =>
                    'Ya existe otro mapping con esa clave.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | VALIDAR CUENTA
        |--------------------------------------------------------------------------
        */

        $account = AccountingAccount::query()
            ->where(
                'id',
                $request->accounting_account_id
            )
            ->where(
                'id_company',
                $user->id_company
            )
            ->where(
                'status',
                true
            )
            ->where(
                'allows_entries',
                true
            )
            ->first();

        if (!$account) {

            return response()->json([
                'success' => false,
                'message' =>
                    'La cuenta contable seleccionada no es válida.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | ACTUALIZAR
        |--------------------------------------------------------------------------
        */

        $mapping->update([

            'key' =>
                strtoupper(
                    trim($request->key)
                ),

            'name' =>
                trim($request->name),

            'accounting_account_id' =>
                $account->id,

            'description' =>
                $request->description
                    ? trim($request->description)
                    : null,

            'status' =>
                (bool) $request->status,
        ]);

        $mapping->load([
            'accountingAccount.accountType',
            'accountingAccount.parent',
        ]);

        return response()->json([
            'success' => true,
            'message' =>
                'Mapping contable actualizado correctamente.',
            'data' => $mapping,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CAMBIAR ESTADO
    |--------------------------------------------------------------------------
    */

    public function toggleStatus(
        Request $request,
        $id
    ) {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $mapping = AccountingAccountMapping::query()
            ->where(
                'id_company',
                $user->id_company
            )
            ->find($id);

        if (!$mapping) {

            return response()->json([
                'success' => false,
                'message' =>
                    'Mapping contable no encontrado.',
            ], 404);
        }

        $mapping->status =
            !$mapping->status;

        $mapping->save();

        return response()->json([
            'success' => true,
            'message' =>
                $mapping->status
                    ? 'Mapping activado correctamente.'
                    : 'Mapping desactivado correctamente.',
            'data' => $mapping,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ELIMINAR
    |--------------------------------------------------------------------------
    */

    public function destroy(
        Request $request,
        $id
    ) {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $mapping = AccountingAccountMapping::query()
            ->where(
                'id_company',
                $user->id_company
            )
            ->find($id);

        if (!$mapping) {

            return response()->json([
                'success' => false,
                'message' =>
                    'Mapping contable no encontrado.',
            ], 404);
        }

        $mapping->delete();

        return response()->json([
            'success' => true,
            'message' =>
                'Mapping contable eliminado correctamente.',
        ]);
    }
}