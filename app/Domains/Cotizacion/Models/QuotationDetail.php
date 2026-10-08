<?php

namespace App\Domains\Cotizacion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Domains\Inventario\Models\Product;


class QuotationDetail extends Model
{
    use HasFactory;

    protected $table = 'quotation_details';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    /*
    |--------------------------------------------------------------------------
    | FILLABLE
    |--------------------------------------------------------------------------
    */

    protected $fillable = [

        'quotation_id',

        'product_id',

        'quantity',

        'price',

        'subtotal',

    ];

    /*
    |--------------------------------------------------------------------------
    | CASTS
    |--------------------------------------------------------------------------
    */

    protected $casts = [

        'quantity' => 'decimal:2',

        'price' => 'decimal:2',

        'subtotal' => 'decimal:2',

    ];

    /*
    |--------------------------------------------------------------------------
    | QUOTATION
    |--------------------------------------------------------------------------
    */

    public function quotation()
    {
        return $this->belongsTo(
            Quotation::class,
            'quotation_id',
            'id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PRODUCT
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
}