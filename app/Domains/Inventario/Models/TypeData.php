<?php

namespace App\Domains\Inventario\Models;

use Illuminate\Database\Eloquent\Model;
use App\Domains\Empresa\Models\Company;
class TypeData extends Model
{
     protected $table = 'type_data';


    /*
    |--------------------------------------------------------------------------
    | Fillable
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'id_type_data',
        'id_company',
        'status',
        'type_name',
        'code',
    ];


    /*
    |--------------------------------------------------------------------------
    | Casts
    |--------------------------------------------------------------------------
    */

    protected $casts = [
        'status' => 'boolean',
    ];


    /*
    |--------------------------------------------------------------------------
    | Relación con Company
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
    | Relación con Products
    |--------------------------------------------------------------------------
    */

    public function products()
    {
        return $this->belongsToMany(
            Product::class,
            'type_product_product',
            'type_data_id',
            'product_id'
        )
        ->withPivot([
            'id',
            'id_company',
            'status'
        ])
        ->withTimestamps();
    }
public function typeDataProducts()
{
    return $this->hasMany(
        TypeDataProduct::class,
        'type_data_id',
        'id'
    );
}
}
