<?php

namespace App\Domains\Inventario\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Domains\Empresa\Models\Company;
use App\Domains\Empresa\Models\Branch;
use App\Domains\Inventario\Models\Product;

class StockProduct extends Model
{
   use HasFactory;

    protected $table = 'product_stock';

    protected $fillable = [
        'id_company',
        'branch_id',
        'product_id',
        'current_stock',
        'status',
        'price',
        'location',
    ];

    protected $casts = [
        'current_stock' => 'decimal:2',
        'price' => 'decimal:2',
        'status' => 'boolean',
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
    | SUCURSAL
    |--------------------------------------------------------------------------
    */

    public function branch()
    {
        return $this->belongsTo(
            Branch::class,
            'branch_id',
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

/*
{
    "id_branch": "SUC-00001",
    "product_id": 1,
    "current_stock": 20,
    "status": true,
    "price": 150.00,
    "location": "A-01"
}
*/