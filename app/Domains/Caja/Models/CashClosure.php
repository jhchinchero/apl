<?php

namespace App\Domains\Caja\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Domains\Empresa\Models\Company;
use App\Domains\Empresa\Models\Branch;
use App\Domains\Seguridad\Models\User;
use App\Domains\Contabilidad\Models\AccountingPeriod;
class CashClosure extends Model
{
   use HasFactory;

    protected $table = 'cash_closures';

    protected $fillable = [
        'id_company',
        'branch_id',
        'cash_register_id',
        'cash_register_session_id',
        'user_id',
        'closed_by',
        'date',
        'opening_amount',
        'total_income',
        'total_expense',
        'expected_amount',
        'counted_amount',
        'difference',
        'status',
        'opening_at',
        'closed_at',
        'note',
    ];

    protected $casts = [
        'date' => 'datetime',
        'opening_amount' => 'decimal:2',
        'total_income' => 'decimal:2',
        'total_expense' => 'decimal:2',
        'expected_amount' => 'decimal:2',
        'counted_amount' => 'decimal:2',
        'difference' => 'decimal:2',
        'opening_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    // ==========================================================
    // EMPRESA
    // ==========================================================

    public function company()
    {
        return $this->belongsTo(
            Company::class,
            'id_company',
            'id_company'
        );
    }


    // ==========================================================
    // SUCURSAL
    // ==========================================================

    public function branch()
    {
        return $this->belongsTo(
            Branch::class,
            'branch_id',
            'id'
        );
    }


    // ==========================================================
    // CAJA
    // ==========================================================

    public function cashRegister()
    {
        return $this->belongsTo(
            CashRegister::class,
            'cash_register_id',
            'id'
        );
    }


    // ==========================================================
    // SESIÓN DE CAJA
    // ==========================================================

    public function cashRegisterSession()
    {
        return $this->belongsTo(
            CashRegisterSession::class,
            'cash_register_session_id',
            'id'
        );
    }


    // ==========================================================
    // USUARIO QUE REALIZA EL CIERRE
    // ==========================================================

    public function user()
    {
        return $this->belongsTo(
            User::class,
            'user_id',
            'id'
        );
    }


    // ==========================================================
    // PERÍODO CONTABLE
    // ==========================================================

    /* public function accountingPeriod()
    {
        return $this->belongsTo(
            AccountingPeriod::class,
            'accounting_period_id',
            'id'
        );
    } */


    // ==========================================================
    // MOVIMIENTOS
    // ==========================================================

    public function movements()
    {
        return $this->hasMany(
            CashMovement::class,
            'cash_closure_id',
            'id'
        );
    }
      public function closedBy()
    {
        return $this->belongsTo(
            User::class,
            'closed_by',
            'id'
        );
    }
}