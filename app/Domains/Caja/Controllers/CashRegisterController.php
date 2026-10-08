<?php

namespace App\Domains\Caja\Controllers;



use App\Http\Controllers\Controller;
use App\Domains\Caja\Models\CashRegister;
use App\Domains\Contabilidad\Models\AccountingAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CashRegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | LISTAR CAJAS
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $user = Auth::user();

        $companyId = $user->id_company;

        $cashRegisters = CashRegister::where(
                'id_company',
                $companyId
            )
            ->with([
                'branch',
                'pointOfSale',
                'accountingAccount',
                'accountingAccount.accountType'
            ])
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $cashRegisters
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | CREAR CAJA
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $user = Auth::user();

        $companyId = $user->id_company;

        $validated = $request->validate([

            'code' => [
                'required',
                'string',
                'max:50'
            ],

            'name' => [
                'required',
                'string',
                'max:100'
            ],

            'description' => [
                'nullable',
                'string'
            ],

            'branch_id' => [
                'required',
                'integer'
            ],

            'point_of_sale_id' => [
                'required',
                'integer'
            ],

            /*
            |--------------------------------------------------------------------------
            | CUENTA CONTABLE
            |--------------------------------------------------------------------------
            */

            'accounting_account_id' => [
                'required',
                'integer',
                'exists:accounting_accounts,id'
            ],
        ]);


        /*
        |--------------------------------------------------------------------------
        | VERIFICAR QUE LA CUENTA PERTENEZCA A LA EMPRESA
        |--------------------------------------------------------------------------
        */

        $accountingAccount = AccountingAccount::where(
                'id',
                $validated['accounting_account_id']
            )
            ->where(
                'id_company',
                $companyId
            )
            ->where(
                'status',
                true
            )
            ->first();

        if (!$accountingAccount) {

            return response()->json([
                'success' => false,
                'message' =>
                    'La cuenta contable seleccionada no pertenece a esta empresa o está inactiva.'
            ], 422);
        }


        /*
        |--------------------------------------------------------------------------
        | VERIFICAR CÓDIGO
        |--------------------------------------------------------------------------
        */

        $exists = CashRegister::where(
                'id_company',
                $companyId
            )
            ->where(
                'code',
                $validated['code']
            )
            ->exists();

        if ($exists) {

            return response()->json([
                'success' => false,
                'message' =>
                    'El código de caja ya existe en esta empresa.'
            ], 422);
        }


        /*
        |--------------------------------------------------------------------------
        | CREAR
        |--------------------------------------------------------------------------
        */

        $cashRegister = CashRegister::create([

            'id_company' =>
                $companyId,

            'code' =>
                $validated['code'],

            'name' =>
                $validated['name'],

            'description' =>
                $validated['description'] ?? null,

            'branch_id' =>
                $validated['branch_id'],

            'point_of_sale_id' =>
                $validated['point_of_sale_id'],

            /*
            |--------------------------------------------------------------------------
            | GUARDAR ID CUENTA CONTABLE
            |--------------------------------------------------------------------------
            */

            'accounting_account_id' =>
                $validated['accounting_account_id'],

            'status' => true,
        ]);


        /*
        |--------------------------------------------------------------------------
        | CARGAR RELACIONES
        |--------------------------------------------------------------------------
        */

        $cashRegister->load([
            'branch',
            'pointOfSale',
            'accountingAccount'
        ]);


        return response()->json([
            'success' => true,

            'message' =>
                'Caja creada correctamente.',

            'data' =>
                $cashRegister
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

        $user = Auth::user();

        $companyId = $user->id_company;


        /*
        |--------------------------------------------------------------------------
        | BUSCAR CAJA
        |--------------------------------------------------------------------------
        */

        $cashRegister = CashRegister::where(
                'id',
                $id
            )
            ->where(
                'id_company',
                $companyId
            )
            ->first();


        if (!$cashRegister) {

            return response()->json([
                'success' => false,
                'message' =>
                    'La caja no existe.'
            ], 404);
        }


        /*
        |--------------------------------------------------------------------------
        | VALIDAR
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([

            'code' => [
                'required',
                'string',
                'max:50'
            ],

            'name' => [
                'required',
                'string',
                'max:100'
            ],

            'description' => [
                'nullable',
                'string'
            ],

            'branch_id' => [
                'required',
                'integer'
            ],

            'point_of_sale_id' => [
                'required',
                'integer'
            ],

            'accounting_account_id' => [
                'required',
                'integer',
                'exists:accounting_accounts,id'
            ],
        ]);


        /*
        |--------------------------------------------------------------------------
        | VERIFICAR CUENTA CONTABLE
        |--------------------------------------------------------------------------
        */

        $accountingAccount = AccountingAccount::where(
                'id',
                $validated['accounting_account_id']
            )
            ->where(
                'id_company',
                $companyId
            )
            ->where(
                'status',
                true
            )
            ->first();


        if (!$accountingAccount) {

            return response()->json([
                'success' => false,
                'message' =>
                    'La cuenta contable seleccionada no pertenece a esta empresa o está inactiva.'
            ], 422);
        }


        /*
        |--------------------------------------------------------------------------
        | VERIFICAR CÓDIGO
        |--------------------------------------------------------------------------
        */

        $exists = CashRegister::where(
                'id_company',
                $companyId
            )
            ->where(
                'code',
                $validated['code']
            )
            ->where(
                'id',
                '!=',
                $cashRegister->id
            )
            ->exists();


        if ($exists) {

            return response()->json([
                'success' => false,
                'message' =>
                    'El código de caja ya está utilizado.'
            ], 422);
        }


        /*
        |--------------------------------------------------------------------------
        | ACTUALIZAR
        |--------------------------------------------------------------------------
        */

        $cashRegister->update([

            'code' =>
                $validated['code'],

            'name' =>
                $validated['name'],

            'description' =>
                $validated['description'] ?? null,

            'branch_id' =>
                $validated['branch_id'],

            'point_of_sale_id' =>
                $validated['point_of_sale_id'],

            /*
            |--------------------------------------------------------------------------
            | ACTUALIZAR CUENTA CONTABLE
            |--------------------------------------------------------------------------
            */

            'accounting_account_id' =>
                $validated['accounting_account_id'],
        ]);


        /*
        |--------------------------------------------------------------------------
        | CARGAR RELACIONES
        |--------------------------------------------------------------------------
        */

        $cashRegister->load([
            'branch',
            'pointOfSale',
            'accountingAccount'
        ]);


        return response()->json([
            'success' => true,

            'message' =>
                'Caja actualizada correctamente.',

            'data' =>
                $cashRegister
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | ACTIVAR / DESACTIVAR
    |--------------------------------------------------------------------------
    */

    public function toggleStatus($id)
    {
        $user = Auth::user();

        $companyId = $user->id_company;

        $cashRegister = CashRegister::where(
                'id',
                $id
            )
            ->where(
                'id_company',
                $companyId
            )
            ->first();


        if (!$cashRegister) {

            return response()->json([
                'success' => false,
                'message' =>
                    'La caja no existe.'
            ], 404);
        }


        /*
        |--------------------------------------------------------------------------
        | CAMBIAR ESTADO
        |--------------------------------------------------------------------------
        */

        $cashRegister->status =
            !$cashRegister->status;

        $cashRegister->save();


        return response()->json([
            'success' => true,

            'message' =>
                $cashRegister->status
                    ? 'Caja activada correctamente.'
                    : 'Caja desactivada correctamente.',

            'data' => [
                'id' =>
                    $cashRegister->id,

                'status' =>
                    (bool) $cashRegister->status
            ]
        ]);
    }
}