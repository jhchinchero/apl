<?php

namespace App\Domains\Inventario\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class ProductExpiration extends Model
{
 use HasFactory;

    protected $table = 'product_expirations';

    protected $fillable = [
        'product_id',
        'lot',
        'expiration_date',
    ];

    protected $casts = [
        'expiration_date' => 'date',
    ];

    public function product()
    {
        return $this->belongsTo(
            Product::class,
            'product_id',
            'id'
        );
    }
}
//
/*
REGISTRAR
ProductExpiration::create([
    'product_id' => $product->id,
    'lot' => 'LOT-001',
    'expiration_date' => '2026-12-30',
]);
*/