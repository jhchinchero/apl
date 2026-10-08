<?php

namespace App\Domains\Caja\Controllers\CashRegister;


use App\Domains\Caja\Models\CashRegister;
use App\Domains\Caja\Models\CashRegisterSession;
use App\Domains\Contabilidad\Models\AccountingAccount;
use App\Domains\Empresa\Models\Branch;
use App\Domains\Empresa\Models\PointOfSale;
use App\Domains\Seguridad\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserCashRegisterController extends Controller
{
    /*
    | id_company es un string (ej. EMP-00002): comparar siempre como string.
    | NO usar (int), porque "EMP-00002" y "EMP-00005" darían ambos 0.
    */
    private function sameCompany($a, $b): bool
    {
        return (string) $a === (string) $b;
    }

    /*
    | Cuentas contables de la empresa (id, code, name)
    | Tolera columnas code/codigo y name/nombre, y filtra por empresa si existe id_company.
    */
    private function accountingAccounts($companyId)
    {
        $table = (new AccountingAccount())->getTable();

        return AccountingAccount::query()
            ->when(
                Schema::hasColumn($table, 'id_company'),
                fn ($q) => $q->where('id_company', $companyId)
            )
            ->get()
            ->map(fn ($a) => [
                'id'   => $a->id,
                'code' => $a->code ?? $a->codigo ?? null,
                'name' => $a->name ?? $a->nombre ?? null,
            ])
            ->sortBy('code')
            ->values();
    }

    /*
    | Filas [branch_id, point_of_sale_id] del usuario (tabla user_branches)
    */
    private function userAssignments(User $user)
    {
        return DB::table('user_branches')
            ->where('user_id', $user->id)
            ->whereNotNull('point_of_sale_id')
            ->get(['branch_id', 'point_of_sale_id']);
    }

    /*
    |--------------------------------------------------------------------------
    | GET users/{user}/cash-register
    |--------------------------------------------------------------------------
    | Sucursales del usuario -> POS -> cajas activas, y la caja asignada
    */
    public function show(User $user)
    {
        $companyId = Auth::user()->id_company;

        abort_unless($this->sameCompany($user->id_company, $companyId), 404);

        $rows = $this->userAssignments($user);

        $branchIds = $rows->pluck('branch_id')->unique()->values();
        $posIds    = $rows->pluck('point_of_sale_id')->unique()->values();

        $posById = PointOfSale::whereIn('id', $posIds)
            ->with(['cashRegisters' => function ($q) use ($companyId) {
                $q->where('id_company', $companyId)
                    ->where('status', true)
                    ->with('accountingAccount')
                    ->orderBy('name');
            }])
            ->get()
            ->keyBy('id');

        $branches = Branch::whereIn('id', $branchIds)
            ->get()
            ->map(function ($branch) use ($rows, $posById) {
                $ids = $rows
                    ->where('branch_id', $branch->id)
                    ->pluck('point_of_sale_id')
                    ->unique();

                $branch->setRelation(
                    'pointOfSales',
                    $ids->map(fn ($id) => $posById->get($id))->filter()->values()
                );

                return $branch;
            })
            ->values();

        $assignedId = DB::table('cash_register_user')
            ->where('id_company', $companyId)
            ->where('user_id', $user->id)
            ->where('status', true)
            ->value('cash_register_id');

        return response()->json([
            'success' => true,
            'data'    => [
                'branches'            => $branches,
                'assigned_id'         => $assignedId,
                'accounting_accounts' => $this->accountingAccounts($companyId),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | POST point-of-sales/{pointOfSale}/cash-registers
    |--------------------------------------------------------------------------
    | Crear una caja en un punto de venta
    */
    public function store(Request $request, PointOfSale $pointOfSale)
    {
        $companyId = Auth::user()->id_company;

        abort_unless($this->sameCompany($pointOfSale->id_company, $companyId), 403);

        $data = $request->validate([
            'name'                  => 'required|string|max:100',
            'description'           => 'nullable|string|max:255',
            'accounting_account_id' => 'nullable|integer',
        ]);

        $accountId = $data['accounting_account_id'] ?? null;

        // La cuenta contable debe existir y ser de la empresa
        if ($accountId && !$this->accountingAccounts($companyId)->contains('id', $accountId)) {
            return response()->json([
                'success' => false,
                'message' => 'La cuenta contable no existe o no pertenece a la empresa.',
            ], 422);
        }

        $next = CashRegister::where('point_of_sale_id', $pointOfSale->id)->count() + 1;

        $cash = CashRegister::create([
            'id_company'            => $companyId,
            'branch_id'             => $pointOfSale->branch_id,
            'point_of_sale_id'      => $pointOfSale->id,
            'code'                  => sprintf('CAJ-%d-%02d', $pointOfSale->id, $next),
            'name'                  => $data['name'],
            'description'           => $data['description'] ?? null,
            'accounting_account_id' => $accountId,
            'status'                => true,
        ]);

        $cash->load('accountingAccount');

        return response()->json([
            'success' => true,
            'message' => 'Caja creada correctamente.',
            'data'    => $cash,
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | POST users/{user}/cash-register
    |--------------------------------------------------------------------------
    | body: { cash_register_id: int|null }  (null = quitar caja)
    | Un usuario solo puede tener una caja activa.
    */
   public function assign(Request $request, User $user)
{
    $companyId = Auth::user()->id_company;

    abort_unless(
        $this->sameCompany($user->id_company, $companyId),
        404
    );

    $data = $request->validate([
        'cash_register_ids'   => 'nullable|array',
        'cash_register_ids.*' => 'integer',
    ]);

    $cashRegisterIds = collect($data['cash_register_ids'] ?? [])
        ->map(fn ($id) => (int) $id)
        ->unique()
        ->values();

    /*
    |--------------------------------------------------------------------------
    | Validar que todas las cajas existan y pertenezcan a la empresa
    |--------------------------------------------------------------------------
    */

    if ($cashRegisterIds->isNotEmpty()) {

        $cashRegisters = CashRegister::query()
            ->whereIn('id', $cashRegisterIds)
            ->where('id_company', $companyId)
            ->where('status', true)
            ->get();

        if ($cashRegisters->count() !== $cashRegisterIds->count()) {
            return response()->json([
                'success' => false,
                'message' => 'Una o más cajas no existen o no pertenecen a la empresa.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Validar que todas las cajas pertenezcan a POS asignados al usuario
        |--------------------------------------------------------------------------
        */

        $allowedPosIds = $this->userAssignments($user)
            ->pluck('point_of_sale_id')
            ->map(fn ($id) => (int) $id);

        $invalidCash = $cashRegisters->first(
            fn ($cash) => !$allowedPosIds->contains((int) $cash->point_of_sale_id)
        );

        if ($invalidCash) {
            return response()->json([
                'success' => false,
                'message' => 'Una de las cajas pertenece a un punto de venta que el usuario no tiene asignado.',
            ], 422);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Validar sesiones abiertas
    |--------------------------------------------------------------------------
    |
    | Si una caja actualmente asignada tiene sesión abierta y se intenta
    | quitarla, no permitimos la operación.
    |
    */

    $currentIds = DB::table('cash_register_user')
        ->where('id_company', $companyId)
        ->where('user_id', $user->id)
        ->where('status', true)
        ->pluck('cash_register_id')
        ->map(fn ($id) => (int) $id);

    $removedIds = $currentIds
        ->diff($cashRegisterIds)
        ->values();

    if ($removedIds->isNotEmpty()) {

        $hasOpenSession = CashRegisterSession::where('user_id', $user->id)
            ->whereIn('cash_register_id', $removedIds)
            ->where('status', 'OPEN')
            ->exists();

        if ($hasOpenSession) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario tiene una sesión abierta en una de las cajas que intenta quitar. Debe cerrarla antes de cambiar las asignaciones.',
            ], 422);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Guardar asignaciones
    |--------------------------------------------------------------------------
    */

    DB::transaction(function () use (
        $companyId,
        $user,
        $cashRegisterIds
    ) {

        /*
        | Primero desactivamos todas las asignaciones actuales
        */
        DB::table('cash_register_user')
            ->where('id_company', $companyId)
            ->where('user_id', $user->id)
            ->where('status', true)
            ->update([
                'status'     => false,
                'updated_at' => now(),
            ]);

        /*
        | Reactivamos o creamos las cajas seleccionadas
        */
        foreach ($cashRegisterIds as $cashRegisterId) {

            $row = DB::table('cash_register_user')
                ->where('id_company', $companyId)
                ->where('cash_register_id', $cashRegisterId)
                ->where('user_id', $user->id)
                ->first();

            if ($row) {

                DB::table('cash_register_user')
                    ->where('id', $row->id)
                    ->update([
                        'status'     => true,
                        'updated_at' => now(),
                    ]);

            } else {

                DB::table('cash_register_user')->insert([
                    'id_company'       => $companyId,
                    'cash_register_id' => $cashRegisterId,
                    'user_id'          => $user->id,
                    'status'           => true,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
            }
        }
    });

    return response()->json([
        'success' => true,
        'message' => $cashRegisterIds->isEmpty()
            ? 'Todas las cajas fueron retiradas del usuario.'
            : 'Cajas asignadas correctamente.',
        'cash_register_ids' => $cashRegisterIds,
    ]);
}
}