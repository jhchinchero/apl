<?php

namespace App\Domains\Inventario\Services;

use App\Domains\Empresa\Models\Branch;
use App\Domains\Inventario\Models\Product;

class ProductReportService
{
    public function getProductsWithStock(string $idCompany): array
    {
        $branches = Branch::where('id_company', $idCompany)
            ->where('status', true)
            ->orderBy('id')
            ->get([
                'id',
                'code_branch',
                'branch_name',
            ]);

        $products = Product::where('id_company', $idCompany)
            ->orderBy('id')
            ->with([
                'stocks' => function ($query) use ($idCompany) {
                    $query
                        ->where('id_company', $idCompany)
                        ->where('status', true);
                }
            ])
            ->get([
                'id',
                'code_product',
                'product_code',
                'name',
                'sale_price',
            ]);

        return [
            'branches' => $branches,
            'products' => $products,
        ];
    }
}