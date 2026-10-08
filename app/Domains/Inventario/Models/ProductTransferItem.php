<?php

namespace App\Domains\Inventario\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Domains\Empresa\Models\Company;

class ProductTransferItem extends Model
{
    use HasFactory;

    protected $table = 'product_transfer_items';

    protected $fillable = [
        'id_company',
        'product_transfer_id',
        'product_id',
        'quantity',
        'price',
        'observation',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'price' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | EMPRESA
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
    | TRANSFERENCIA
    |--------------------------------------------------------------------------
    */

    public function transfer()
    {
        return $this->belongsTo(
            ProductTransfer::class,
            'product_transfer_id',
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
}