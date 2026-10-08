<?php

namespace App\Domains\Ventas\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Domains\Inventario\Models\Product;
use App\Domains\Ventas\Models\Sale;

use App\Domains\Ventas\Models\SaleReturn;
use App\Domains\Ventas\Models\SalePayment;
use App\Domains\Ventas\Models\SaleFiscalData;
use App\Domains\Clientes\Models\ClientBalanceMovement;

use App\Domains\Ventas\Models\SaleReturnDetail;

class SaleDetail extends Model
{
    use HasFactory;

    protected $table = 'sale_details';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'code_sale_detail',
        'product_id',
        'sale_id',
        'quantity',
        'unit_price',
        'subtotal',
        'discount',
        'iva',
        'total',
        'returned',
        'returned_quantity',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'iva' => 'decimal:2',
        'total' => 'decimal:2',
        'returned' => 'boolean',
        'returned_quantity' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | VENTA
    |--------------------------------------------------------------------------
    */

    public function sale()
    {
        return $this->belongsTo(
            Sale::class,
            'sale_id',
            'id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PRODUCTO
    |--------------------------------------------------------------------------
    */

    public function product()
    {
        return $this->belongsTo(
            Product::class,
            'product_id',
            'id'
        );
    }
    //////////////////////////////////
 public function returnItems()
    {
        return $this->hasMany(
            SaleReturnItem::class,
            'sale_detail_id',
            'id'
        );
    }

    public function payments()
    {
        return $this->hasMany(
            SalePayment::class,
            'sale_id',
            'id'
        );
    }

    public function fiscalData()
    {
        return $this->hasOne(
            SaleFiscalData::class,
            'sale_id',
            'id'
        );
    }

    public function balanceMovements()
    {
        return $this->hasMany(
            ClientBalanceMovement::class,
            'sale_id',
            'id'
        );
    }

}
