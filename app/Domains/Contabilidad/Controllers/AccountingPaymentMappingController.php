<?php

namespace App\Domains\Contabilidad\Controllers;

use App\Http\Controllers\Controller;

use App\Domains\Contabilidad\Models\AccountingPaymentMapping;
use App\Domains\Contabilidad\Models\AccountingAccount;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AccountingPaymentMappingController extends Controller
{
    private array $paymentMethods = [
        'CASH',
        'CARD',
        'TRANSFER',
        'QR',
        'CHECK',
        'OTHER',
    ];

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

        $query = AccountingPaymentMapping::query()
            ->where(
                'id_company',
                $user->id_company
            )
            ->with([
                'accountingAccount.accountType',
                'accountingAccount.parent',
            ])
            ->orderBy('payment_method');

        if ($request->filled('payment_method')) {

            $query->where(
                'payment_method',
                strtoupper(
                    trim($request->payment_method)
                )
            );
        }

        if ($request->has('status')) {

            $query->where(
                'status',
                filter_var(
                    $request->status,
                    FILTER_VALIDATE_BOOLEAN
                )
            );
        }

        return response()->json([
            'success' => true,
            'data' => $query->get(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | MÉTODOS DISPONIBLES
    |--------------------------------------------------------------------------
    */

    public function methods()
    {
        return response()->json([
            'success' => true,
            'data' => collect($this->paymentMethods)
                ->map(function ($method) {

                    return [
                        'code' => $method,
                        'name' => $this->paymentMethodName($method),
                    ];
                })
                ->values(),
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

        $mapping = AccountingPaymentMapping::query()
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
                'message' =>
                    'Configuración del método de pago no encontrada.',
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
                'payment_method' => [
                    'required',
                    'string',
                    'in:' . implode(',', $this->paymentMethods),
                ],

                'accounting_account_id' => [
                    'required',
                    'integer',
                ],

                'status' => [
                    'nullable',
                    'boolean',
                ],
            ]
        );

        if ($validator->fails()) {

            return response()->json([
                'success' => false,
                'message' =>
                    'Los datos enviados no son válidos.',
                'errors' =>
                    $validator->errors(),
            ], 422);
        }

        $paymentMethod = strtoupper(
            trim($request->payment_method)
        );

        /*
        |--------------------------------------------------------------------------
        | EVITAR DUPLICADO
        |--------------------------------------------------------------------------
        */

        $exists = AccountingPaymentMapping::query()
            ->where(
                'id_company',
                $user->id_company
            )
            ->where(
                'payment_method',
                $paymentMethod
            )
            ->exists();

        if ($exists) {

            return response()->json([
                'success' => false,
                'message' =>
                    "Ya existe una cuenta configurada para {$paymentMethod}.",
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
            ->with('accountType')
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
        | CREAR
        |--------------------------------------------------------------------------
        */

        $mapping = AccountingPaymentMapping::create([
            'id_company' =>
                $user->id_company,

            'payment_method' =>
                $paymentMethod,

            'accounting_account_id' =>
                $account->id,

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
                'Configuración del método de pago creada correctamente.',
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

        $mapping = AccountingPaymentMapping::query()
            ->where(
                'id_company',
                $user->id_company
            )
            ->find($id);

        if (!$mapping) {

            return response()->json([
                'success' => false,
                'message' =>
                    'Configuración no encontrada.',
            ], 404);
        }

        $validator = Validator::make(
            $request->all(),
            [
                'payment_method' => [
                    'required',
                    'string',
                    'in:' . implode(',', $this->paymentMethods),
                ],

                'accounting_account_id' => [
                    'required',
                    'integer',
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
                'message' =>
                    'Los datos enviados no son válidos.',
                'errors' =>
                    $validator->errors(),
            ], 422);
        }

        $paymentMethod = strtoupper(
            trim($request->payment_method)
        );

        /*
        |--------------------------------------------------------------------------
        | DUPLICADO
        |--------------------------------------------------------------------------
        */

        $exists = AccountingPaymentMapping::query()
            ->where(
                'id_company',
                $user->id_company
            )
            ->where(
                'payment_method',
                $paymentMethod
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
                    "Ya existe otra configuración para {$paymentMethod}.",
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

        $mapping->update([
            'payment_method' =>
                $paymentMethod,

            'accounting_account_id' =>
                $account->id,

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
                'Configuración actualizada correctamente.',
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

        $mapping = AccountingPaymentMapping::query()
            ->where(
                'id_company',
                $user->id_company
            )
            ->find($id);

        if (!$mapping) {

            return response()->json([
                'success' => false,
                'message' =>
                    'Configuración no encontrada.',
            ], 404);
        }

        $mapping->status =
            !$mapping->status;

        $mapping->save();

        return response()->json([
            'success' => true,
            'message' =>
                $mapping->status
                    ? 'Configuración activada correctamente.'
                    : 'Configuración desactivada correctamente.',
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

        $mapping = AccountingPaymentMapping::query()
            ->where(
                'id_company',
                $user->id_company
            )
            ->find($id);

        if (!$mapping) {

            return response()->json([
                'success' => false,
                'message' =>
                    'Configuración no encontrada.',
            ], 404);
        }

        $mapping->delete();

        return response()->json([
            'success' => true,
            'message' =>
                'Configuración eliminada correctamente.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | NOMBRE DEL MÉTODO
    |--------------------------------------------------------------------------
    */

    private function paymentMethodName(
        string $method
    ): string {

        return match ($method) {

            'CASH' =>
                'Efectivo',

            'CARD' =>
                'Tarjeta',

            'TRANSFER' =>
                'Transferencia',

            'QR' =>
                'QR',

            'CHECK' =>
                'Cheque',

            'OTHER' =>
                'Otro',

            default =>
                $method,
        };
    }
}