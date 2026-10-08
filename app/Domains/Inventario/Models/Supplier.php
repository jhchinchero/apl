<?php

namespace App\Domains\Inventario\Models;

use Illuminate\Database\Eloquent\Model;
use App\Domains\Empresa\Models\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class Supplier extends Model
{
    use HasFactory;

    protected $table = 'suppliers';


    /*
    |--------------------------------------------------------------------------
    | Fillable
    |--------------------------------------------------------------------------
    */

    protected $fillable = [

        'code_supplier',

        'id_company',

        'status',

        'name',

        'email',

        'phone',

        'address',

        'reference',
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
    | Empresa
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
    | Productos
    |--------------------------------------------------------------------------
    */

    public function products()
    {
        return $this->hasMany(
            Product::class,
            'supplier_id',
            'id'
        );
    }
}