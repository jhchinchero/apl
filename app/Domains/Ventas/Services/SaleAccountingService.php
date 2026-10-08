<?php

namespace App\Domains\Ventas\Services;

use App\Domains\Contabilidad\Models\AccountingAccount;
use App\Domains\Contabilidad\Models\AccountingEntry;
use App\Domains\Contabilidad\Models\AccountingEntryDetail;
use App\Domains\Contabilidad\Models\AccountingPeriod;
use App\Domains\Ventas\Models\Sale;
use Illuminate\Support\Collection;
use RuntimeException;

class SaleAccountingService
{
    public function __construct(
        private readonly SaleNumberService $numberService
    ) {}

    public function createSaleEntry(
        Sale $sale,
        array $payments,
        float $usedBalance,
        float $total,
        float $costOfGoodsSold,
        int $branchId,
        string $idCompany,
        int $userId
    ): AccountingEntry {
        $amounts = $this->calculateAmounts($payments, $usedBalance);

        $expectedDebit = round(
            $total + $costOfGoodsSold,
            2
        );

        if (
            abs(
                $amounts['total_debit'] + $costOfGoodsSold
                    - $expectedDebit
            ) > 0.01
        ) {
            throw new RuntimeException(
                'El asiento contable de la venta no cuadra.'
            );
        }

        $period = AccountingPeriod::query()
            ->where('id_company', $idCompany)
            ->where('status', 'OPEN')
            ->first();

        if (!$period) {
            throw new RuntimeException(
                'No existe un período contable abierto para registrar la venta.'
            );
        }

        /* $cashAccount = $this->account($idCompany, '1.1.01', 'CAJA');
        $bankAccount = $this->account($idCompany, '1.1.02', 'BANCOS');
        $receivableAccount = $this->account(
            $idCompany,
            '1.1.03',
            'CUENTAS POR COBRAR'
        );
        $salesAccount = $this->account($idCompany, '4.1', 'VENTAS'); */
        $cashAccount = $this->account(
            $idCompany,
            '1.1.01',
            'CAJA'
        );

        $bankAccount = $this->account(
            $idCompany,
            '1.1.02',
            'BANCOS'
        );

        $receivableAccount = $this->account(
            $idCompany,
            '1.1.03',
            'CUENTAS POR COBRAR'
        );

        $salesAccount = $this->account(
            $idCompany,
            '4.1.01',
            'VENTAS'
        );

        $costOfGoodsSoldAccount = $this->account(
            $idCompany,
            '5.1.01',
            'COSTO DE VENTAS'
        );

        $inventoryAccount = $this->account(
            $idCompany,
            '1.1.04',
            'INVENTARIOS'
        );

        $entry = AccountingEntry::create([
            'id_company' => $idCompany,
            'branch_id' => $branchId,
            'accounting_period_id' => $period->id,
            'code_entry' => $this->numberService
                ->nextAccountingEntryCode($idCompany),
            'date' => now(),
            'type' => 'VENTA',
            'description' => 'Venta ' . $sale->code_sale,
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
            'user_id' => $userId,
            'status' => 'POSTED',
        ]);

        if ($amounts['cash'] > 0) {
            $this->detail(
                $entry,
                $cashAccount,
                'Ingreso efectivo por venta ' . $sale->code_sale,
                $amounts['cash'],
                0
            );
        }

        if ($amounts['bank'] > 0) {
            $this->detail(
                $entry,
                $bankAccount,
                'Pago electrónico por venta ' . $sale->code_sale,
                $amounts['bank'],
                0
            );
        }

        if ($amounts['balance'] > 0) {
            $this->detail(
                $entry,
                $receivableAccount,
                'Saldo utilizado del cliente en venta ' . $sale->code_sale,
                $amounts['balance'],
                0
            );
        }

        $this->detail(
            $entry,
            $salesAccount,
            'Venta ' . $sale->code_sale,
            0,
            round($total, 2)
        );
        if ($costOfGoodsSold > 0) {

            $this->detail(
                $entry,
                $costOfGoodsSoldAccount,
                'Costo de venta ' . $sale->code_sale,
                round($costOfGoodsSold, 2),
                0
            );

            $this->detail(
                $entry,
                $inventoryAccount,
                'Salida de inventario por venta ' . $sale->code_sale,
                0,
                round($costOfGoodsSold, 2)
            );
        }

        return $entry;
    }

    private function calculateAmounts(
        array $payments,
        float $usedBalance
    ): array {
        $cash = 0;
        $bank = 0;

        foreach ($payments as $payment) {
            $method = strtoupper($payment->payment_method);
            $amount = round((float) $payment->amount, 2);

            if ($method === 'CASH') {
                $cash += $amount;
                continue;
            }

            if (in_array(
                $method,
                ['CARD', 'TRANSFER', 'QR', 'CHECK', 'OTHER'],
                true
            )) {
                $bank += $amount;
            }
        }

        $balance = round($usedBalance, 2);

        return [
            'cash' => round($cash, 2),
            'bank' => round($bank, 2),
            'balance' => $balance,
            'total_debit' => round($cash + $bank + $balance, 2),
        ];
    }

    private function account(
        string $idCompany,
        string $code,
        string $name
    ): AccountingAccount {
        $account = AccountingAccount::query()
            ->where('id_company', $idCompany)
            ->where('code', $code)
            ->where('status', true)
            ->first();

        if (!$account) {
            throw new RuntimeException(
                "No existe la cuenta contable {$code} {$name}."
            );
        }

        return $account;
    }

    private function detail(
        AccountingEntry $entry,
        AccountingAccount $account,
        string $description,
        float $debit,
        float $credit
    ): void {
        AccountingEntryDetail::create([
            'accounting_entry_id' => $entry->id,
            'accounting_account_id' => $account->id,
            'description' => $description,
            'debit' => round($debit, 2),
            'credit' => round($credit, 2),
        ]);
    }
}
