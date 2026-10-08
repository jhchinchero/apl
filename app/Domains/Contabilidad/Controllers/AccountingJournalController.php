<?php

namespace App\Domains\Contabilidad\Controllers;

use App\Http\Controllers\Controller;

use App\Domains\Contabilidad\Services\AccountingJournalService;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountingJournalController extends Controller
{
    public function __construct(
        protected AccountingJournalService $journalService
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

        $data = $this->journalService->getJournal(
            $request->all()
        );

        return response()->json([
            'success' => true,
            'message' => 'Libro diario obtenido correctamente.',
            'data' => $data
        ]);
    }
}