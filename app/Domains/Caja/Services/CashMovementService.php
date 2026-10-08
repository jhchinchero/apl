<?php

namespace App\Domains\Caja\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use App\Domains\Caja\Models\CashMovement;
use App\Domains\Caja\Models\CashRegisterSession;
use App\Domains\Caja\Models\CashCategory;
use App\Domains\Contabilidad\Services\AccountingService;

class CashMovementService
{
    public function __construct(
        protected AccountingService $accountingService
    ) {}

    public function create(array $data): CashMovement
    {
        return DB::transaction(function () use ($data) {

            $session = CashRegisterSession::query()
                ->where('id', $data['cash_register_session_id'])
                ->where('id_company', $data['id_company'])
                ->where('status', 'OPEN')
                ->lockForUpdate()
                ->first();

            if (!$session) {
                throw ValidationException::withMessages([
                    'cash_register_session_id' =>
                        'La sesión de caja no existe o está cerrada.'
                ]);
            }

            $category = CashCategory::query()
                ->where('id', $data['cash_category_id'])
                ->where('id_company', $data['id_company'])
                ->where('status', true)
                ->with('accountingAccount')
                ->first();

            if (!$category) {
                throw ValidationException::withMessages([
                    'cash_category_id' =>
                        'La categoría de caja no existe.'
                ]);
            }

            if ($category->type !== $data['type']) {
                throw ValidationException::withMessages([
                    'type' =>
                        'El tipo del movimiento no coincide con la categoría.'
                ]);
            }

            $movement = CashMovement::create([
                'id_company' => $data['id_company'],
                'branch_id' => $session->branch_id,
                'cash_register_id' => $session->cash_register_id,
                'cash_register_session_id' =>
                    $session->id,
                'user_id' => $data['user_id'],
                'cash_category_id' =>
                    $category->id,
                'type' => $data['type'],
                'amount' => $data['amount'],
                'description' =>
                    $data['description'] ?? null,
                'sale_id' =>
                    $data['sale_id'] ?? null,
                'sale_return_id' =>
                    $data['sale_return_id'] ?? null,
                'sale_payment_id' =>
                    $data['sale_payment_id'] ?? null,
            ]);

            /*
            |--------------------------------------------------------------
            | ASIENTO CONTABLE
            |--------------------------------------------------------------
            */

            if (!$category->accounting_account_id) {
                throw ValidationException::withMessages([
                    'cash_category_id' =>
                        'La categoría no tiene cuenta contable configurada.'
                ]);
            }

            $cashAccountId = $this->getCashAccount(
                $data['id_company']
            );

            if ($data['type'] === 'INGRESO') {

                $details = [

                    [
                        'accounting_account_id' =>
                            $cashAccountId,
                        'description' =>
                            'Ingreso a caja',
                        'debit' =>
                            $data['amount'],
                        'credit' =>
                            0,
                    ],

                    [
                        'accounting_account_id' =>
                            $category->accounting_account_id,
                        'description' =>
                            $category->name,
                        'debit' =>
                            0,
                        'credit' =>
                            $data['amount'],
                    ],
                ];

            } else {

                $details = [

                    [
                        'accounting_account_id' =>
                            $category->accounting_account_id,
                        'description' =>
                            $category->name,
                        'debit' =>
                            $data['amount'],
                        'credit' =>
                            0,
                    ],

                    [
                        'accounting_account_id' =>
                            $cashAccountId,
                        'description' =>
                            'Egreso de caja',
                        'debit' =>
                            0,
                        'credit' =>
                            $data['amount'],
                    ],
                ];
            }

            $entry = $this->accountingService->createEntry([

                'id_company' =>
                    $data['id_company'],

                'branch_id' =>
                    $session->branch_id,

                'date' =>
                    now(),

                'type' =>
                    'CAJA_' . $data['type'],

                'description' =>
                    $data['description'] ??
                    $category->name,

                'reference_type' =>
                    CashMovement::class,

                'reference_id' =>
                    $movement->id,

                'user_id' =>
                    $data['user_id'],

                'details' =>
                    $details,
            ]);

            return $movement->load([
                'category',
                'cashRegister',
                'session',
            ]);
        });
    }

    private function getCashAccount(string $companyId): int
    {
        $account = \App\Domains\Contabilidad\Models\AccountingAccount::query()
            ->where('id_company', $companyId)
            ->where('code', '1.1.01')
            ->where('status', true)
            ->first();

        if (!$account) {
            throw ValidationException::withMessages([
                'accounting_account' =>
                    'No existe la cuenta contable 1.1.01 CAJA.'
            ]);
        }

        return $account->id;
    }
}