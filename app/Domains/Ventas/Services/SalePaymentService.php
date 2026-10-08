<?php

namespace App\Domains\Ventas\Services;

use App\Domains\Caja\Models\CashCategory;
use App\Domains\Caja\Models\CashMovement;
use App\Domains\Caja\Models\CashRegisterSession;
use App\Domains\Ventas\Models\Sale;
use App\Domains\Ventas\Models\SalePayment;
use Illuminate\Contracts\Auth\Authenticatable;
use RuntimeException;

class SalePaymentService
{
    public function getCashSessionIfRequired(
        array $payments,
        Authenticatable $user,
        string $idCompany,
        int $branchId
    ): ?CashRegisterSession {
        $requiresCash = collect($payments)->contains(
            fn ($payment) => strtoupper($payment['payment_method']) === 'CASH'
        );

        if (!$requiresCash) {
            return null;
        }

        $session = CashRegisterSession::query()
            ->with('cashRegister')
            ->where('id_company', $idCompany)
            ->where('user_id', $user->id)
            ->where('status', 'OPEN')
            ->lockForUpdate()
            ->first();

        if (!$session) {
            throw new RuntimeException(
                'No puede registrar la venta en efectivo porque no tiene una caja abierta.'
            );
        }

        if (!$session->cashRegister) {
            throw new RuntimeException(
                'La sesión de caja no tiene una caja asociada.'
            );
        }

        if (
            isset($session->cashRegister->branch_id) &&
            (int) $session->cashRegister->branch_id !== $branchId
        ) {
            throw new RuntimeException(
                'La caja abierta no pertenece a la sucursal seleccionada.'
            );
        }

        return $session;
    }

    public function createPayments(
        Sale $sale,
        array $payments,
        ?CashRegisterSession $cashSession,
        string $idCompany,
        int $branchId,
        int $userId
    ): array {
        $created = [];

        foreach ($payments as $data) {
            $method = strtoupper($data['payment_method']);
            $amount = round((float) $data['amount'], 2);

            $paymentData = [
                'id_company' => $idCompany,
                'sale_id' => $sale->id,
                'payment_method' => $method,
                'amount' => $amount,
                'reference' => $data['reference'] ?? null,
                'observation' => $data['observation'] ?? null,
            ];

            if ($cashSession) {
                $paymentData['cash_register_id'] =
                    $cashSession->cash_register_id;

                $paymentData['cash_register_session_id'] =
                    $cashSession->id;
            }

            $payment = SalePayment::create($paymentData);
            $created[] = $payment;

            if ($method === 'CASH') {
                $this->createCashMovement(
                    sale: $sale,
                    payment: $payment,
                    cashSession: $cashSession,
                    amount: $amount,
                    idCompany: $idCompany,
                    branchId: $branchId,
                    userId: $userId
                );
            }
        }

        return $created;
    }

    private function createCashMovement(
        Sale $sale,
        SalePayment $payment,
        ?CashRegisterSession $cashSession,
        float $amount,
        string $idCompany,
        int $branchId,
        int $userId
    ): void {
        if (!$cashSession) {
            throw new RuntimeException(
                'No existe una sesión de caja abierta para registrar el pago en efectivo.'
            );
        }

        $category = CashCategory::query()
            ->where('id_company', $idCompany)
            ->where('type', 'INGRESO')
            ->where('status', true)
            ->where(function ($query) {
                $query->where('code', 'VENTA_EFECTIVO')
                    ->orWhere('name', 'VENTA EFECTIVO')
                    ->orWhere('name', 'Venta Efectivo');
            })
            ->first();

        if (!$category) {
            throw new RuntimeException(
                'No existe la categoría de caja "VENTA EFECTIVO" para esta empresa.'
            );
        }

        CashMovement::create([
            'id_company' => $idCompany,
            'branch_id' => $branchId,
            'cash_register_id' => $cashSession->cash_register_id,
            'cash_register_session_id' => $cashSession->id,
            'user_id' => $userId,
            'cash_category_id' => $category->id,
            'type' => 'INGRESO',
            'amount' => $amount,
            'sale_id' => $sale->id,
            'sale_return_id' => null,
            'sale_payment_id' => $payment->id,
            'description' => 'Ingreso por venta ' . $sale->code_sale . ' - Efectivo',
        ]);
    }
}
