<?php

namespace App\Domains\Caja\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Domains\Empresa\Models\Company;
use App\Domains\Empresa\Models\Branch;
use App\Domains\Seguridad\Models\User;
use App\Domains\Empresa\Models\PointOfSale;
use App\Domains\Caja\Models\CashMovement;
use App\Domains\Caja\Models\CashRegisterSession;
use App\Domains\Contabilidad\Models\AccountingAccount;


class CashRegister extends Model
{
    use HasFactory;

    protected $table = 'cash_registers';

    protected $fillable = [
        'id_company',
        'branch_id',
        'point_of_sale_id',
        'code',
        'name',
        'description',
        'status',
        'accounting_account_id'
    ];

    protected $casts = [
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
    | PUNTO DE VENTA
    |--------------------------------------------------------------------------
    */

    public function pointOfSale()
    {
        return $this->belongsTo(
            PointOfSale::class,
            'point_of_sale_id',
            'id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | USUARIOS ASIGNADOS
    |--------------------------------------------------------------------------
    */

    public function users()
    {
        return $this->belongsToMany(
            User::class,
            'cash_register_user',
            'cash_register_id',
            'user_id'
        )
            ->withPivot([
                'id_company',
                'status',
            ])
            ->withTimestamps();
    }

    /*
    |--------------------------------------------------------------------------
    | SESIONES / APERTURAS DE CAJA
    |--------------------------------------------------------------------------
    */

    public function sessions()
    {
        return $this->hasMany(
            CashRegisterSession::class,
            'cash_register_id',
            'id'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | SESIÓN ABIERTA ACTUAL
    |--------------------------------------------------------------------------
    */

    public function openSession()
    {
        return $this->hasOne(
            CashRegisterSession::class,
            'cash_register_id',
            'id'
        )->where('status', 'OPEN');
    }
    public function movements()
    {
        return $this->hasMany(
            CashMovement::class,
            'cash_register_id',
            'id'
        );
    }

    public function accountingAccount()
    {
        return $this->belongsTo(
            AccountingAccount::class,
            'accounting_account_id',
            'id'
        );
    }
}
