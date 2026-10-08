<?php

namespace App\Domains\Contabilidad\Controllers\Seeder;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Domains\Contabilidad\Services\Seeder\AccountingSetupService;

class AccountingConfigurationSeederController extends Controller
{
   public function initialize(
    Request $request,
    AccountingSetupService $setupService
) {
    $user = $request->user();

    $companyId = $user->id_company;

    if (!$companyId) {
        return response()->json([
            'success' => false,
            'message' => 'El usuario no tiene una empresa asociada.'
        ], 422);
    }

    $result = $setupService->setup($companyId);

    if ($result['already_initialized'] ?? false) {

        return response()->json(
            $result,
            409
        );
    }

    return response()->json(
        $result,
        201
    );
}
}
