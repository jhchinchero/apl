<?php

namespace App\Domains\Empresa\Models;

use Illuminate\Database\Eloquent\Model;
use App\Domains\Empresa\Models\Company;
use App\Domains\Seguridad\Models\User;
use App\Domains\Inventario\Models\StockProduct;
use App\Domains\Caja\Models\CashMovement;
use App\Domains\Inventario\Models\ProductTransfer;
USE App\Domains\Empresa\Models\PointOfSale;

class Branch extends Model
{
    protected $table = 'branches';

    protected $fillable = [
        'code_branch',
        'codigo',
        'id_company',
        'status',
        'branch_name',
        'department',
        'municipality',
        'address',
        'contact_name',
        'phone'
    ];

    /*
    |--------------------------------------------------------------------------
    | COMPANY
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
    | USERS
    |--------------------------------------------------------------------------
    */

    /*  public function users()
    {
        return $this->hasMany(
            User::class,
            'branch_id',
            'id'
        );
    } */
    public function users()
    {
        return $this->belongsToMany(
            User::class,
            'user_branches',
            'branch_id',
            'user_id'
        )
            ->withPivot('point_of_sale_id')
            ->withTimestamps();
    }

    /*
    |--------------------------------------------------------------------------
    | PRODUCT STOCK
    |--------------------------------------------------------------------------
    */

    public function stocks()
    {
        return $this->hasMany(
            StockProduct::class,
            'branch_id',
            'id'
        );
    }
    public function pointOfSales()
    {
        return $this->hasMany(
            PointOfSale::class,
            'branch_id',
            'id'
        );
    }
    public function cashMovements()
    {
        return $this->hasMany(
            CashMovement::class,
            'branch_id',
            'id'
        );
    }
    /*
|--------------------------------------------------------------------------
| TRANSFERENCIAS DE ORIGEN
|--------------------------------------------------------------------------
*/

    public function outgoingTransfers()
    {
        return $this->hasMany(
            ProductTransfer::class,
            'origin_branch_id',
            'id'
        );
    }

    /*
|--------------------------------------------------------------------------
| TRANSFERENCIAS DE DESTINO
|--------------------------------------------------------------------------
*/

    public function incomingTransfers()
    {
        return $this->hasMany(
            ProductTransfer::class,
            'destination_branch_id',
            'id'
        );
    }
}
