<?php

namespace App\Domains\Inventario\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Domains\Empresa\Models\Company;
use App\Domains\Ventas\Models\SaleDetail;
use  App\Domains\Ventas\Models\SaleReturnItem;
use App\Domains\Inventario\Models\ProductTransferItem;
use App\Domains\Cotizacion\Models\QuotationDetail;

class Product extends Model
{
    use HasFactory;

    protected $table = 'products';


    /*
    |--------------------------------------------------------------------------
    | Primary Key
    |--------------------------------------------------------------------------
    |
    | Laravel utiliza "id" como PK interna.
    |
    */

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';


    /*
    |--------------------------------------------------------------------------
    | Fillable
    |--------------------------------------------------------------------------
    */

    protected $fillable = [

        'code_product',

        'id_company',

        'model_product',

        'brand_id',

        'supplier_id',

        'category_id',

        'name',

        'lote',

        'description',

        'status',

        'sale_price',

        'purchase_price',

        'unit_of_measure',

        'product_code',
        'codigo_producto_sin',
        'actividad_codigo',
    ];


    /*
    |--------------------------------------------------------------------------
    | Casts
    |--------------------------------------------------------------------------
    */

    protected $casts = [

        'status' => 'boolean',

        'sale_price' => 'decimal:2',

        'purchase_price' => 'decimal:2',
    ];


    /*
    |--------------------------------------------------------------------------
    | Company
    |--------------------------------------------------------------------------
    */

    public function company()
    {
        return $this->belongsTo(
            Company::class,
            'id_company',
            'id_company'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Brand
    |--------------------------------------------------------------------------
    */

    public function brand()
    {
        return $this->belongsTo(
            Brand::class,
            'brand_id',
            'id'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Supplier
    |--------------------------------------------------------------------------
    */

    public function supplier()
    {
        return $this->belongsTo(
            Supplier::class,
            'supplier_id',
            'id'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Category
    |--------------------------------------------------------------------------
    */

    public function category()
    {
        return $this->belongsTo(
            Category::class,
            'category_id',
            'id'
        );
    }



    public function typeDataProducts()
    {
        return $this->hasMany(
            TypeDataProduct::class,
            'product_id',
            'id'
        );
    }
    public function typeData()
    {
        return $this->belongsToMany(
            TypeData::class,
            'type_product_product',
            'product_id',
            'type_data_id'
        )
            ->withPivot([
                'id',
                'id_company',
                'status'
            ])
            ->withTimestamps();
    }

    public function expirations()
    {
        return $this->hasMany(
            ProductExpiration::class,
            'product_id',
            'id'
        );
    }

    public function images()
    {
        return $this->hasMany(
            Image::class,
            'product_id',
            'id'
        );
    }
    /*
    |--------------------------------------------------------------------------
    | STOCK
    |--------------------------------------------------------------------------
    */

    public function stocks()
    {
        return $this->hasMany(
            StockProduct::class,
            'product_id',
            'id'
        );
    }
    /*
    |--------------------------------------------------------------------------
    | SALE DETAILS
    |--------------------------------------------------------------------------
    */

    public function saleDetails()
    {
        return $this->hasMany(
            SaleDetail::class,
            'product_id',
            'id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | RETURN ITEMS
    |--------------------------------------------------------------------------
    */

    public function saleReturnItems()
    {
        return $this->hasMany(
            SaleReturnItem::class,
            'product_id',
            'id'
        );
    }
    /*
|--------------------------------------------------------------------------
| TRANSFER ITEMS
|--------------------------------------------------------------------------
*/

    public function transferItems()
    {
        return $this->hasMany(
            ProductTransferItem::class,
            'product_id',
            'id'
        );
    }
    public function quotationDetails()
    {
        return $this->hasMany(
            QuotationDetail::class,
            'product_id',
            'id'
        );
    }
}
