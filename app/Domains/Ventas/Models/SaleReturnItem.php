<?php

namespace App\Domains\Ventas\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Domains\Inventario\Models\Product;

class SaleReturnItem extends Model
{
    use HasFactory;

    protected $table = 'sale_return_items';

    protected $fillable = [
        'sale_return_id',
        'sale_detail_id',
        'product_id',
        'quantity',
        'unit_price',
        'subtotal',
        'discount',
        'iva',
        'total',
        'reason',
    ];

    protected $casts = [
        'quantity'   => 'decimal:2',
        'unit_price' => 'decimal:2',
        'subtotal'   => 'decimal:2',
        'discount'   => 'decimal:2',
        'iva'        => 'decimal:2',
        'total'      => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | DEVOLUCIÓN
    |--------------------------------------------------------------------------
    */

    public function saleReturn()
    {
        return $this->belongsTo(
            SaleReturn::class,
            'sale_return_id',
            'id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | DETALLE DE VENTA
    |--------------------------------------------------------------------------
    */

    public function saleDetail()
    {
        return $this->belongsTo(
            SaleDetail::class,
            'sale_detail_id',
            'id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PRODUCTO
    |--------------------------------------------------------------------------
    |
    | sale_return_items.product_id
    |              ↓
    | products.id
    |
    */

    public function product()
    {
        return $this->belongsTo(
            Product::class,
            'product_id',
            'id'
        );
    }
}