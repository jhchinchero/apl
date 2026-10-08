<?php

namespace App\Domains\Contabilidad\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Contabilidad\Services\AccountingLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountingLedgerController extends Controller
{
    public function __construct(
        protected AccountingLedgerService $ledgerService
    ) {
    }

    public function index(Request $request)
    {
        $user = Auth::user();

        if (!$user) {

            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.'
            ], 401);
        }

        if (!$user->id_company) {

            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una empresa asignada.'
            ], 422);
        }

        $validated = $request->validate([
            'account_id' => [
                'nullable',
                'integer'
            ],

            'start_date' => [
                'nullable',
                'date'
            ],

            'end_date' => [
                'nullable',
                'date'
            ],

            'branch_id' => [
                'nullable'
            ],
        ]);

        $result = $this->ledgerService->getLedger(
            $validated
        );

        return response()->json([
            'success' => true,
            'message' => 'Libro mayor obtenido correctamente.',
            'data' => $result
        ]);
    }
}