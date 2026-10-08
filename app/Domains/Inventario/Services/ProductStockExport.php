<?php

namespace App\Domains\Inventario\Services;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProductStockExport implements
    FromArray,
    WithHeadings,
    ShouldAutoSize
{
    protected $branches;
    protected $products;

    public function __construct($branches, $products)
    {
        $this->branches = $branches;
        $this->products = $products;
    }

    public function headings(): array
    {
        $headings = [
            'Código',
            'Producto',
            'Precio',
        ];

        foreach ($this->branches as $branch) {
            $headings[] = $branch->code_branch;
        }

        return $headings;
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->products as $product) {

            $name = $product->name ?? '';

            // Máximo 20 caracteres + ...
            if (mb_strlen($name) > 20) {
                $name = mb_substr($name, 0, 20) . '...';
            }

            $row = [
                $product->product_code ?: $product->code_product,
                $name,
                (float) $product->sale_price,
            ];

            foreach ($this->branches as $branch) {

                $stock = 0;

                $stockRecord = $product->stocks
                    ->firstWhere('branch_id', $branch->id);

                if ($stockRecord) {
                    $stock = $stockRecord->current_stock;
                }

                $row[] = (float) $stock;
            }

            $rows[] = $row;
        }

        return $rows;
    }
}