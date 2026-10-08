<?php

namespace App\Domains\Contabilidad\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Contabilidad\Services\FinancialStatementService;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FinancialStatementController extends Controller
{
    public function __construct(
        protected FinancialStatementService $statementService
    ) {
    }

    /**
     * ============================================================
     * ESTADO DE RESULTADOS
     * ============================================================
     */
    public function incomeStatement(Request $request)
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
            'start_date' => [
                'required',
                'date'
            ],

            'end_date' => [
                'required',
                'date',
                'after_or_equal:start_date'
            ],
        ]);

        try {

            $result = $this->statementService->incomeStatement(
                $user->id_company,
                $validated['start_date'],
                $validated['end_date']
            );

            return response()->json([
                'success' => true,
                'message' => 'Estado de resultados obtenido correctamente.',
                'data' => $result
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el estado de resultados.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * ============================================================
     * BALANCE GENERAL
     * ============================================================
     */
    public function balanceSheet(Request $request)
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
            'start_date' => [
                'required',
                'date'
            ],

            'end_date' => [
                'required',
                'date',
                'after_or_equal:start_date'
            ],
        ]);

        try {

            $result = $this->statementService->balanceSheet(
                $user->id_company,
                $validated['start_date'],
                $validated['end_date']
            );

            return response()->json([
                'success' => true,
                'message' => 'Balance general obtenido correctamente.',
                'data' => $result
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el balance general.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}