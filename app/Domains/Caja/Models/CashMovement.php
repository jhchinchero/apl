<?php

namespace App\Domains\Caja\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Domains\Empresa\Models\Company;
use App\Domains\Empresa\Models\Branch;
use App\Domains\Seguridad\Models\User;
use App\Domains\Caja\Models\CashRegister;
use App\Domains\Caja\Models\CashRegisterSession;
use App\Domains\Ventas\Models\Sale;
use App\Domains\Ventas\Models\SalePayment;
use App\Domains\Ventas\Models\SaleReturn;

class CashMovement extends Model
{
  use HasFactory;

    protected $table = 'cash_movements';

    protected $fillable = [
        'id_company',
        'branch_id',
        'cash_register_id',
        'cash_register_session_id',
        'user_id',
        'cash_category_id',
        'type',
        'amount',
        'sale_id',
        'sale_return_id',
        'sale_payment_id',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
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
    | CAJA
    |--------------------------------------------------------------------------
    */

    public function cashRegister()
    {
        return $this->belongsTo(
            CashRegister::class,
            'cash_register_id',
            'id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SESIÓN DE CAJA
    |--------------------------------------------------------------------------
    */

    public function cashRegisterSession()//sesssion
    {
        return $this->belongsTo(
            CashRegisterSession::class,
            'cash_register_session_id',
            'id'
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
    | VENTA (YA NO ES NECESARIO )
    |--------------------------------------------------------------------------
    */

   /*  public function sale()
    {
        return $this->belongsTo(
            Sale::class,
            'sale_id',
            'id'
        );
    } */
    public function category()
    {
        return $this->belongsTo(
            CashCategory::class,
            'cash_category_id',
            'id'
        );
    }

    public function sale()
    {
        return $this->belongsTo(
            Sale::class,
            'sale_id',
            'id'
        );
    }

    public function saleReturn()
    {
        return $this->belongsTo(
            SaleReturn::class,
            'sale_return_id',
            'id'
        );
    }

    public function salePayment()
    {
        return $this->belongsTo(
            SalePayment::class,
            'sale_payment_id',
            'id'
        );
    }
}