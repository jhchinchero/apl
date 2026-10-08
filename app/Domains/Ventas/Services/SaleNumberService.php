<?php

namespace App\Domains\Ventas\Services;

use App\Domains\Ventas\Models\Sale;
use App\Domains\Ventas\Models\SaleDetail;
use App\Domains\Contabilidad\Models\AccountingEntry;
use Illuminate\Support\Facades\DB;

class SaleNumberService
{
    public function generateSaleCodes(string $idCompany): array
    {
        $lastSale = Sale::query()
            ->where('id_company', $idCompany)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        $saleNumber = $this->nextNumber(
            $lastSale?->code_sale,
            'VEN-',
            6
        );

        $lastReceipt = Sale::query()
            ->where('id_company', $idCompany)
            ->whereNotNull('receipt_number')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        $receiptNumber = $this->nextNumber(
            $lastReceipt?->receipt_number,
            '',
            6
        );

        return [
            'code_sale' => 'VEN-' . str_pad($saleNumber, 6, '0', STR_PAD_LEFT),
            'receipt_number' => str_pad(
                $receiptNumber,
                6,
                '0',
                STR_PAD_LEFT
            ),
        ];
    }

    public function nextSaleDetailCode(): string
    {
        $lastDetail = SaleDetail::query()
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        $number = $this->nextNumber(
            $lastDetail?->code_sale_detail,
            'VD-',
            6
        );

        return 'VD-' . str_pad($number, 6, '0', STR_PAD_LEFT);
    }

    public function nextAccountingEntryCode(string $idCompany): string
    {
        $lastEntry = AccountingEntry::query()
            ->where('id_company', $idCompany)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        $number = $this->nextNumber(
            $lastEntry?->code_entry,
            'AST-',
            6
        );

        return 'AST-' . str_pad($number, 6, '0', STR_PAD_LEFT);
    }

    private function nextNumber(
        ?string $value,
        string $prefix = '',
        int $padding = 6
    ): int {
        if (!$value) {
            return 1;
        }

        preg_match('/(\d+)$/', $value, $matches);

        return isset($matches[1])
            ? ((int) $matches[1]) + 1
            : 1;
    }
}
