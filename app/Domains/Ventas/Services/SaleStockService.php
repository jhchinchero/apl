<?php

namespace App\Domains\Ventas\Services;

use App\Domains\Inventario\Models\Product;
use App\Domains\Inventario\Models\StockProduct;
use App\Domains\Ventas\Models\Sale;
use App\Domains\Ventas\Models\SaleDetail;
use Illuminate\Support\Collection;
use App\Domains\Inventario\Services\StockMovementService;
use RuntimeException;

class SaleStockService
{
    public function __construct(
        private readonly SaleNumberService $numberService,
        private readonly StockMovementService $stockMovementService
    ) {
    }

    public function validateItems(
        array $items,
        string $idCompany,
        int $branchId
    ): array {
        /*
         * Primero agrupamos productos repetidos.
         * Esto evita que dos líneas del mismo producto consuman
         * el mismo stock sin comprobar el total.
         */
        $grouped = [];

        foreach ($items as $item) {
            $productId = (int) $item['product_id'];
            $quantity = round((float) $item['quantity'], 2);
            $unitPrice = round((float) $item['price'], 2);

            if (!isset($grouped[$productId])) {
                $grouped[$productId] = [
                    'quantity' => 0,
                    'lines' => [],
                ];
            }

            $grouped[$productId]['quantity'] += $quantity;
            $grouped[$productId]['lines'][] = [
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
            ];
        }

        $validated = [];

        foreach ($grouped as $productId => $group) {
            $product = Product::query()
                ->where('id', $productId)
                ->where('id_company', $idCompany)
                ->where('status', true)
                ->lockForUpdate()
                ->first();

            if (!$product) {
                throw new RuntimeException(
                    "El producto con ID {$productId} no existe, está inactivo o no pertenece a la empresa."
                );
            }

            $stock = StockProduct::query()
                ->where('id_company', $idCompany)
                ->where('branch_id', $branchId)
                ->where('product_id', $productId)
                ->where('status', true)
                ->lockForUpdate()
                ->first();

            if (!$stock) {
                throw new RuntimeException(
                    "No existe stock configurado para el producto {$product->name} en la sucursal seleccionada."
                );
            }

            $currentStock = round((float) $stock->current_stock, 2);
            $required = round($group['quantity'], 2);

            if ($currentStock < $required) {
                throw new RuntimeException(
                    "Stock insuficiente para {$product->name}. Disponible: {$currentStock}. Solicitado: {$required}."
                );
            }

            $validated[] = [
                'product' => $product,
                'stock' => $stock,
                'lines' => $group['lines'],
            ];
        }

        return $validated;
    }

   /*  public function decreaseAndCreateDetails(
        Sale $sale,
        array $items
    ): void {
        foreach ($items as $itemGroup) {
            $stock = $itemGroup['stock'];
            $product = $itemGroup['product'];

            $totalQuantity = 0;

            foreach ($itemGroup['lines'] as $line) {
                $quantity = round((float) $line['quantity'], 2);
                $unitPrice = round((float) $line['unit_price'], 2);
                $subtotal = round($quantity * $unitPrice, 2);

                $code = $this->numberService->nextSaleDetailCode();

                SaleDetail::create([
                    'code_sale_detail' => $code,
                    'product_id' => $product->id,
                    'sale_id' => $sale->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                    'discount' => 0,
                    'iva' => 0,
                    'total' => $subtotal,
                    'returned' => false,
                    'returned_quantity' => 0,
                ]);

                $totalQuantity += $quantity;
            }

            $stock->current_stock = round(
                (float) $stock->current_stock - $totalQuantity,
                2
            );

            $stock->save();
        }
    } */
   public function decreaseAndCreateDetails(
    Sale $sale,
    array $items
): void {
    foreach ($items as $itemGroup) {

        $stock = $itemGroup['stock'];
        $product = $itemGroup['product'];

        $totalQuantity = 0;

        /*
         * ============================================================
         * 1. CREAR DETALLES DE LA VENTA
         * ============================================================
         */
        foreach ($itemGroup['lines'] as $line) {

            $quantity = round((float) $line['quantity'], 2);
            $unitPrice = round((float) $line['unit_price'], 2);

            $subtotal = round(
                $quantity * $unitPrice,
                2
            );

            $code = $this->numberService->nextSaleDetailCode();

            SaleDetail::create([
                'code_sale_detail' => $code,
                'product_id' => $product->id,
                'sale_id' => $sale->id,

                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,

                'discount' => 0,
                'iva' => 0,
                'total' => $subtotal,

                'returned' => false,
                'returned_quantity' => 0,
            ]);

            $totalQuantity += $quantity;
        }

        /*
         * ============================================================
         * 2. GUARDAR STOCK ANTES
         * ============================================================
         */
        $stockBefore = round(
            (float) $stock->current_stock,
            2
        );

        /*
         * ============================================================
         * 3. DESCONTAR STOCK
         * ============================================================
         */
        $stock->current_stock = round(
            $stockBefore - $totalQuantity,
            2
        );

       $stock->save();

$movement = $this->stockMovementService->registerSale(
    sale: $sale,
    stock: $stock,
    productId: $product->id,
    quantity: $totalQuantity,
);

if (!$movement->exists) {
    throw new RuntimeException(
        "El movimiento no fue registrado para el producto {$product->id}"
    );
}
    }
}
}
