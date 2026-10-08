<?php

namespace App\Domains\Ventas\Models;

use Illuminate\Database\Eloquent\Model;


use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Domains\Seguridad\Models\User;
use App\Domains\Empresa\Models\Company;
use App\Domains\Empresa\Models\Branch;
use App\Domains\Clientes\Models\Client;
use App\Domains\Inventario\Models\Product;
use App\Domains\Ventas\Models\SaleDetail;



use App\Domains\Ventas\Models\SaleReturn;
use App\Domains\Ventas\Models\SalePayment;
use App\Domains\Ventas\Models\SaleFiscalData;
use App\Domains\Clientes\Models\ClientBalanceMovement;
use App\Domains\Caja\Models\CashMovement;
class Sale extends Model
{
    use HasFactory;

    protected $table = 'sales';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'code_sale',
        'id_company',
        'receipt_number',
        'date',
        'discount',
        'total',
        'pdf_url',
        'user_id',
        'client_id',
        'branch_id',
    ];

    protected $casts = [
        'date' => 'datetime',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
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
    | USUARIO
    |--------------------------------------------------------------------------
    */

    public function user()
    {
        return $this->belongsTo(
            User::class,
            'user_id',
            'id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CLIENTE
    |--------------------------------------------------------------------------
    */

    public function client()
    {
        return $this->belongsTo(
            Client::class,
            'client_id',
            'id'
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
    | DETALLES DE LA VENTA
    |--------------------------------------------------------------------------
    */

    public function details()
    {
        return $this->hasMany(
            SaleDetail::class,
            'sale_id',
            'id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PRODUCTOS
    |--------------------------------------------------------------------------
    */

    public function products()
    {
        return $this->belongsToMany(
            Product::class,
            'sale_details',
            'sale_id',
            'product_id'
        )->withPivot([
            'code_sale_detail',
            'quantity',
            'unit_price',
            'subtotal',
            'discount',
            'iva',
            'total',
            'returned',
            'returned_quantity',
        ])->withTimestamps();
    }
    //////////////////////////////////////////////////////////////////////
    public function returns()
    {
        return $this->hasMany(
            SaleReturn::class,
            'sale_id',
            'id'
        );
    }

    public function payments()
    {
        return $this->hasMany(
            SalePayment::class,
            'sale_id',
            'id'
        );
    }

    public function fiscalData()
    {
        return $this->hasOne(
            SaleFiscalData::class,
            'sale_id',
            'id'
        );
    }

    public function balanceMovements()
    {
        return $this->hasMany(
            ClientBalanceMovement::class,
            'sale_id',
            'id'
        );
    }
    public function cashMovements()
{
    return $this->hasMany(
        CashMovement::class,
        'sale_id',
        'id'
    );
}
}
