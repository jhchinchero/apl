<?php

namespace App\Domains\Inventario\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Domains\Inventario\Models\TypeData;
use App\Domains\Empresa\Models\Company;
class TypeDataProduct extends Model
{
      use HasFactory;

    protected $table = 'type_data_product';

    protected $fillable = [
        'id_company',
        'product_id',
        'type_data_id',
        'value'
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
    | Product
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


    /*
    |--------------------------------------------------------------------------
    | Type Data
    |--------------------------------------------------------------------------
    */

    public function typeData()
{
    return $this->belongsTo(
        TypeData::class,
        'type_data_id',
        'id'
    );
}
}
/*
public function typeData()
{
    return $this->belongsToMany(
        TypeData::class,
        'type_product_product',
        'product_id',
        'type_data_id'
    );
}

$product->typeData;
*/