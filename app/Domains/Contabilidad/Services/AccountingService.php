<?php

namespace App\Domains\Contabilidad\Services;

use App\Domains\Contabilidad\Models\AccountingAccount;
use App\Domains\Contabilidad\Models\AccountingAccountMapping;
use App\Domains\Contabilidad\Models\AccountingPaymentMapping;
use App\Domains\Contabilidad\Models\AccountingEntry;
use App\Domains\Contabilidad\Models\AccountingEntryDetail;
use App\Domains\Caja\Models\CashRegisterSession;

use Illuminate\Support\Facades\DB;
use Exception;

class AccountingService
{
    /**
     * ============================================================
     * OBTENER CUENTA MEDIANTE MAPPING
     * ============================================================
     *
     * Ejemplo:
     *
     * VENTA
     * CUENTA_POR_COBRAR
     * COSTO_VENTA
     * INVENTARIO
     *
     */
    public function getMappedAccount(
        string $companyId,
        string $key
    ): AccountingAccount {

        $mapping = AccountingAccountMapping::query()
            ->where('id_company', $companyId)
            ->where('key', strtoupper($key))
            ->where('status', true)
            ->with('accountingAccount.accountType')
            ->first();

        if (!$mapping) {
            throw new Exception(
                "No existe una cuenta contable configurada para la operación: {$key}"
            );
        }

        $account = $mapping->accountingAccount;

        if (!$account) {
            throw new Exception(
                "La operación {$key} no tiene una cuenta contable asignada."
            );
        }

        $this->validateAccount(
            $account,
            $companyId
        );

        return $account;
    }


    /**
     * ============================================================
     * OBTENER CUENTA SEGÚN MÉTODO DE PAGO
     * ============================================================
     *
     * CASH
     *  → cuenta configurada en cash_registers
     *
     * CARD
     * TRANSFER
     * QR
     * CHECK
     * OTHER
     *  → accounting_payment_mappings
     *
     */
   public function getPaymentAccount(
    string $companyId,
    string $paymentMethod,
    ?CashRegisterSession $cashSession = null
): AccountingAccount {

    $paymentMethod = strtoupper($paymentMethod);

    /*
    |--------------------------------------------------------------------------
    | SALDO / CRÉDITO DEL CLIENTE
    |--------------------------------------------------------------------------
    */

    if ($paymentMethod === 'BALANCE') {
        return $this->getMappedAccount(
            $companyId,
            'CUENTA_POR_COBRAR'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | EFECTIVO
    |--------------------------------------------------------------------------
    */

    if ($paymentMethod === 'CASH') {

        if (!$cashSession) {
            throw new Exception(
                'No existe una sesión de caja para el pago en efectivo.'
            );
        }

        $cashSession->loadMissing(
            'cashRegister.accountingAccount.accountType'
        );

        $cashRegister = $cashSession->cashRegister;

        if (!$cashRegister) {
            throw new Exception(
                'La sesión de caja no tiene una caja asociada.'
            );
        }

        if (
            (string) $cashRegister->id_company !==
            (string) $companyId
        ) {
            throw new Exception(
                'La caja no pertenece a la empresa actual.'
            );
        }

        $account = $cashRegister->accountingAccount;

        if (!$account) {
            throw new Exception(
                'La caja no tiene una cuenta contable asignada.'
            );
        }

        $this->validateAccount(
            $account,
            $companyId
        );

        if (
            !$account->accountType ||
            $account->accountType->code !== 'ACT'
        ) {
            throw new Exception(
                'La cuenta contable de la caja debe pertenecer al tipo ACTIVO.'
            );
        }

        return $account;
    }

    /*
    |--------------------------------------------------------------------------
    | TARJETA / TRANSFERENCIA / QR / CHEQUE / OTROS
    |--------------------------------------------------------------------------
    */

    $mapping = AccountingPaymentMapping::query()
        ->where('id_company', $companyId)
        ->where('payment_method', $paymentMethod)
        ->where('status', true)
        ->with('accountingAccount.accountType')
        ->first();

    if (!$mapping) {
        throw new Exception(
            "No existe una cuenta contable configurada para el método de pago: {$paymentMethod}"
        );
    }

    $account = $mapping->accountingAccount;

    if (!$account) {
        throw new Exception(
            "El método de pago {$paymentMethod} no tiene una cuenta contable asignada."
        );
    }

    $this->validateAccount(
        $account,
        $companyId
    );

    return $account;
}


    /**
     * ============================================================
     * VALIDAR CUENTA
     * ============================================================
     */
    private function validateAccount(
        AccountingAccount $account,
        string $companyId
    ): void {

        if (
            (string) $account->id_company !==
            (string) $companyId
        ) {
            throw new Exception(
                'La cuenta contable no pertenece a la empresa actual.'
            );
        }

        if (!$account->status) {
            throw new Exception(
                "La cuenta contable {$account->code} está inactiva."
            );
        }

        if (!$account->allows_entries) {
            throw new Exception(
                "La cuenta {$account->code} no permite movimientos contables."
            );
        }
    }


    /**
     * ============================================================
     * CREAR ASIENTO CONTABLE
     * ============================================================
     *
     * $data:
     *
     * [
     *     'id_company' => 'EMP-00001',
     *     'branch_id' => 1,
     *     'accounting_period_id' => 1,
     *     'date' => '2026-09-14',
     *     'type' => 'VENTA',
     *     'description' => 'Venta VEN-000001',
     *     'reference_type' => Sale::class,
     *     'reference_id' => 1,
     *     'user_id' => 1,
     *     'status' => 'POSTED'
     * ]
     *
     */
    public function createEntry(
        array $data,
        array $details
    ): AccountingEntry {

        return DB::transaction(function () use (
            $data,
            $details
        ) {

            /*
             * Generar código del asiento.
             */
            $codeEntry = $this->generateEntryCode(
                $data['id_company']
            );

            $entry = AccountingEntry::create([
                'id_company' => $data['id_company'],
                'branch_id' => $data['branch_id'] ?? null,
                'accounting_period_id' =>
                $data['accounting_period_id'],
                'code_entry' => $codeEntry,
                'date' => $data['date'],
                'type' => $data['type'],
                'description' =>
                $data['description'] ?? null,
                'reference_type' =>
                $data['reference_type'] ?? null,
                'reference_id' =>
                $data['reference_id'] ?? null,
                'user_id' => $data['user_id'],
                'status' =>
                $data['status'] ?? 'POSTED',
            ]);


            /*
             * Crear detalles.
             */
            foreach ($details as $detail) {

                $accountId =
                    $detail['accounting_account_id']
                    ?? null;

                if (!$accountId) {
                    throw new Exception(
                        'Uno de los detalles no tiene cuenta contable.'
                    );
                }

                $account =
                    AccountingAccount::query()
                    ->where('id', $accountId)
                    ->where(
                        'id_company',
                        $data['id_company']
                    )
                    ->first();

                if (!$account) {
                    throw new Exception(
                        'La cuenta contable del detalle no pertenece a la empresa.'
                    );
                }

                $this->validateAccount(
                    $account,
                    $data['id_company']
                );


                $debit =
                    round(
                        (float) ($detail['debit'] ?? 0),
                        2
                    );

                $credit =
                    round(
                        (float) ($detail['credit'] ?? 0),
                        2
                    );


                if (
                    $debit < 0 ||
                    $credit < 0
                ) {
                    throw new Exception(
                        'Los valores Debe y Haber no pueden ser negativos.'
                    );
                }


                if (
                    $debit == 0 &&
                    $credit == 0
                ) {
                    throw new Exception(
                        'Un detalle contable no puede tener Debe y Haber en cero.'
                    );
                }


                if (
                    $debit > 0 &&
                    $credit > 0
                ) {
                    throw new Exception(
                        'Un detalle contable no puede tener Debe y Haber simultáneamente.'
                    );
                }


                AccountingEntryDetail::create([
                    'accounting_entry_id' =>
                    $entry->id,

                    'accounting_account_id' =>
                    $account->id,

                    'description' =>
                    $detail['description']
                        ?? null,

                    'debit' => $debit,

                    'credit' => $credit,
                ]);
            }


            /*
             * Validar que el asiento esté cuadrado.
             */
            $totals =
                AccountingEntryDetail::where(
                    'accounting_entry_id',
                    $entry->id
                )
                ->selectRaw(
                    'SUM(debit) as total_debit,
                     SUM(credit) as total_credit'
                )
                ->first();

            $totalDebit =
                round(
                    (float) $totals->total_debit,
                    2
                );

            $totalCredit =
                round(
                    (float) $totals->total_credit,
                    2
                );


            if (
                $totalDebit !==
                $totalCredit
            ) {

                throw new Exception(
                    'El asiento contable no está cuadrado. ' .
                        'Debe: ' . number_format(
                            $totalDebit,
                            2,
                            '.',
                            ''
                        ) .
                        ' / Haber: ' . number_format(
                            $totalCredit,
                            2,
                            '.',
                            ''
                        )
                );
            }


            return $entry->load([
                'details.account'
            ]);
        });
    }


    /**
     * ============================================================
     * GENERAR CÓDIGO DE ASIENTO
     * ============================================================
     */
    private function generateEntryCode(
        string $companyId
    ): string {

        $lastEntry =
            AccountingEntry::query()
            ->where(
                'id_company',
                $companyId
            )
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        if (!$lastEntry) {
            $number = 1;
        } else {

            /*
             * Extraer número de ASI-000001
             */
            preg_match(
                '/(\d+)$/',
                $lastEntry->code_entry,
                $matches
            );

            $number =
                isset($matches[1])
                ? ((int) $matches[1]) + 1
                : ($lastEntry->id + 1);
        }

        return 'ASI-' .
            str_pad(
                $number,
                6,
                '0',
                STR_PAD_LEFT
            );
    }
}

/* use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use App\Domains\Contabilidad\Models\AccountingEntry;
use App\Domains\Contabilidad\Models\AccountingEntryDetail;
use App\Domains\Contabilidad\Models\AccountingPeriod;

class AccountingService
{
    public function createEntry(array $data): AccountingEntry
    {
        return DB::transaction(function () use ($data) {

            $debit = collect($data['details'])
                ->sum(fn ($detail) => (float) ($detail['debit'] ?? 0));

            $credit = collect($data['details'])
                ->sum(fn ($detail) => (float) ($detail['credit'] ?? 0));

            $debit = round($debit, 2);
            $credit = round($credit, 2);

            if ($debit !== $credit) {
                throw ValidationException::withMessages([
                    'accounting' =>
                        'El asiento contable no está cuadrado. ' .
                        "Debe: {$debit} / Haber: {$credit}"
                ]);
            }

            $period = AccountingPeriod::query()
                ->where('id_company', $data['id_company'])
                ->where('start_date', '<=', $data['date'])
                ->where('end_date', '>=', $data['date'])
                ->where('status', 'OPEN')
                ->first();

            if (!$period) {
                throw ValidationException::withMessages([
                    'date' =>
                        'No existe un período contable abierto para esta fecha.'
                ]);
            }

            $code = $this->generateCode(
                $data['id_company']
            );

            $entry = AccountingEntry::create([
                'id_company' => $data['id_company'],
                'branch_id' => $data['branch_id'] ?? null,
                'accounting_period_id' => $period->id,
                'code_entry' => $code,
                'date' => $data['date'],
                'type' => $data['type'],
                'description' => $data['description'] ?? null,
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'user_id' => $data['user_id'],
                'status' => 'POSTED',
            ]);

            foreach ($data['details'] as $detail) {

                AccountingEntryDetail::create([
                    'accounting_entry_id' => $entry->id,
                    'accounting_account_id' =>
                        $detail['accounting_account_id'],
                    'description' =>
                        $detail['description'] ?? null,
                    'debit' =>
                        $detail['debit'] ?? 0,
                    'credit' =>
                        $detail['credit'] ?? 0,
                ]);
            }

            return $entry->load('details');
        });
    }

    private function generateCode(string $companyId): string
    {
        $last = AccountingEntry::query()
            ->where('id_company', $companyId)
            ->orderByDesc('id')
            ->first();

        $number = $last
            ? ((int) preg_replace('/[^0-9]/', '', $last->code_entry)) + 1
            : 1;

        return 'AST-' . str_pad(
            $number,
            6,
            '0',
            STR_PAD_LEFT
        );
    }
} */



    /*
¿Qué conseguimos con esto?

Ahora podremos hacer desde cualquier controlador:

$ventasAccount =
    $accountingService->getMappedAccount(
        $companyId,
        'VENTA'
    );

Y Laravel buscará:

accounting_account_mappings
        │
        │ key = VENTA
        ↓
4.1.01 VENTAS

No importa si mañana cambias:

4.1.01

por:

4.01.001

El controlador no cambia.

3. Obtener cuenta de pago

Por ejemplo:

$account =
    $accountingService->getPaymentAccount(
        $companyId,
        'CARD'
    );

Busca:

accounting_payment_mappings

CARD
 ↓
Banco
 ↓
1.1.02.01

Para efectivo:

$account =
    $accountingService->getPaymentAccount(
        $companyId,
        'CASH',
        $cashSession
    );

Busca:

CashRegisterSession
        ↓
CashRegister
        ↓
accounting_account_id
        ↓
1.1.01 Caja Principal
4. Crear un asiento

Por ejemplo, una venta de Bs 1.000:

$entry =
    $accountingService->createEntry(
        [
            'id_company' => $companyId,
            'branch_id' => $sale->branch_id,
            'accounting_period_id' => $period->id,
            'date' => $sale->date,
            'type' => 'VENTA',
            'description' =>
                'Venta ' . $sale->code_sale,
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
            'user_id' => $user->id,
            'status' => 'POSTED',
        ],
        [
            [
                'accounting_account_id' =>
                    $cashAccount->id,
                'description' =>
                    'Cobro efectivo',
                'debit' => 1000,
                'credit' => 0,
            ],
            [
                'accounting_account_id' =>
                    $salesAccount->id,
                'description' =>
                    'Venta de productos',
                'debit' => 0,
                'credit' => 1000,
            ],
        ]
    );

El resultado:

ASI-000001
────────────────────────────────

DEBE                         HABER

1.1.01 Caja       1.000,00

                             4.1.01 Ventas
                             1.000,00
────────────────────────────────
TOTAL             1.000,00   1.000,00

Y el servicio rechaza automáticamente un asiento descuadrado.

5. Siguiente paso: modificar SaleController

Ahora no debemos modificar todavía todo el controlador a ciegas.

El siguiente cambio será específicamente esta parte:

// ACTUAL
1.1.01
1.1.02
1.1.03
4.1

para convertirla en:

SaleController
       │
       ↓
AccountingService
       │
       ├── VENTA
       ├── CUENTA_POR_COBRAR
       ├── COSTO_VENTA
       ├── INVENTARIO
       │
       └── método de pago
              │
              ├── CASH → Caja
              ├── CARD → Mapping
              ├── TRANSFER → Mapping
              ├── QR → Mapping
              └── CHECK → Mapping

Ese es el siguiente cambio que debemos hacer en tu SaleController, conservando toda tu lógica actual de stock, pagos, caja y clientes.


4. Cuenta de CxC

Si la venta utiliza saldo/crédito:

$receivableAccount = null;

if ((float) $usedBalance > 0) {

    $receivableAccount =
        $this->accountingService->getMappedAccount(
            $idCompany,
            'CUENTA_POR_COBRAR'
        );
}
    */