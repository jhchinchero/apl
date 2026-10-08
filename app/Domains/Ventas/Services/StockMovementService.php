<?php

namespace App\Domains\Inventario\Services;

use App\Domains\Inventario\Models\StockMovement;
use App\Domains\Inventario\Models\StockProduct;
use App\Domains\Ventas\Models\Sale;
use RuntimeException;

class StockMovementService
{
    public function registerSale(
        Sale $sale,
        StockProduct $stock,
        int $productId,
        float $quantity
    ): StockMovement {

        $movement = StockMovement::create([
            'id_company' => $sale->id_company,
            'branch_id' => $sale->branch_id,
            'product_id' => $productId,
            'user_id' => $sale->user_id,

            'movement_type' => 'SALE',
            'quantity' => round($quantity, 2),

            'reference_type' => 'sale',
            'reference_id' => $sale->id,

            'note' => "Venta {$sale->code_sale}",
        ]);

        if (!$movement) {
            throw new RuntimeException(
                'No se pudo registrar el movimiento de stock.'
            );
        }

        return $movement;
    }
}