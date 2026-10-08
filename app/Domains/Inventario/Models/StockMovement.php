<?php

namespace App\Domains\Inventario\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Domains\Empresa\Models\Company;
use App\Domains\Empresa\Models\Branch;
use App\Domains\Seguridad\Models\User;


class StockMovement extends Model
{
 use HasFactory;

    protected $table = 'stock_movements';

    protected $fillable = [

        'id_company',

        'branch_id',

        'product_id',

        'user_id',

        'movement_type',

        'quantity',

        'reference_type',

        'reference_id',

        'note'

    ];


    protected $casts = [

        'quantity' => 'decimal:2'

    ];




    public function company()
    {
        return $this->belongsTo(
            Company::class,
            'id_company',
            'id_company'
        );
    }



    public function branch()
    {
        return $this->belongsTo(
            Branch::class,
            'branch_id',
            'id'
        );
    }



    public function product()
    {
        return $this->belongsTo(
            Product::class,
            'product_id',
            'id'
        );
    }




    public function user()
    {
        return $this->belongsTo(
            User::class,
            'user_id',
            'id'
        );
    }
}
