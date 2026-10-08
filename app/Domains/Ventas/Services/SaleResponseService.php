<?php

namespace App\Domains\Ventas\Services;

use App\Domains\Ventas\Models\Sale;

class SaleResponseService
{
    public function make(
        array $result,
        float $subtotal,
        float $discount,
        float $total,
        float $usedBalance,
        float $totalFinal
    ): array {
        /** @var Sale $sale */
        $sale = $result['sale'];

        $sale->load([
            'company',
            'user',
            'client',
            'branch',
            'details.product',
            'payments',
        ]);

        return [
            'id' => $sale->id,
            'code_sale' => $sale->code_sale,
            'receipt_number' => $sale->receipt_number,
            'id_company' => $sale->id_company,
            'branch_id' => $sale->branch_id,
            'client_id' => $sale->client_id,
            'user_id' => $sale->user_id,

            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => $total,
            'usedBalance' => $usedBalance,
            'totalFinal' => $totalFinal,

            'clientBalanceBefore' =>
                $result['client_balance_before'],

            'clientBalanceAfter' =>
                $result['client_balance_after'],

            'accounting_entry' => [
                'id' => $result['accounting_entry']->id,
                'code_entry' =>
                    $result['accounting_entry']->code_entry,
                'type' => $result['accounting_entry']->type,
                'description' =>
                    $result['accounting_entry']->description,
                'status' => $result['accounting_entry']->status,
            ],

            'payments' => $sale->payments->map(
                function ($payment) {
                    return [
                        'id' => $payment->id,
                        'payment_method' =>
                            $payment->payment_method,
                        'amount' => $payment->amount,
                        'reference' => $payment->reference,
                        'observation' => $payment->observation,
                    ];
                }
            )->values(),

            'stock' => $sale->details->map(
                function ($detail) {
                    return [
                        'product_id' => $detail->product_id,
                        'quantity' => $detail->quantity,
                        'unit_price' => $detail->unit_price,
                        'subtotal' => $detail->subtotal,
                        'total' => $detail->total,
                    ];
                }
            )->values(),
        ];
    }
}
