<?php

namespace App\Domains\Contabilidad\Models;



use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Domains\Empresa\Models\Company;
use App\Domains\Empresa\Models\Branch;
use App\Domains\Seguridad\Models\User;

use App\Domains\Caja\Models\CashRegister;
use App\Domains\Caja\Models\CashRegisterSession;
use App\Domains\Caja\Models\CashCategory;
use App\Domains\Caja\Models\CashMovement;

class IncomeExpense extends Model
{
    use HasFactory;

    /*
    |--------------------------------------------------------------------------
    | TABLA
    |--------------------------------------------------------------------------
    */

    protected $table = 'income_expenses';

    /*
    |--------------------------------------------------------------------------
    | CAMPOS ASIGNABLES
    |--------------------------------------------------------------------------
    */

    protected $fillable = [

        // Empresa
        'id_company',

        // Ubicación / caja
        'branch_id',
        'cash_register_id',
        'cash_register_session_id',

        // Usuario
        'user_id',

        // Categoría
        'cash_category_id',

        // Contabilidad
        'accounting_account_id',
        'accounting_period_id',

        // Referencias generadas
        'cash_movement_id',
        'accounting_entry_id',

        // Información del movimiento
        'code',
        'date',
        'type',
        'amount',
        'description',
        'status',
    ];

    /*
    |--------------------------------------------------------------------------
    | CASTS
    |--------------------------------------------------------------------------
    */

    protected $casts = [

        'date' => 'date',

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

    public function cashRegisterSession()
    {
        return $this->belongsTo(
            CashRegisterSession::class,
            'cash_register_session_id',
            'id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CATEGORÍA DE CAJA
    |--------------------------------------------------------------------------
    */

    public function cashCategory()
    {
        return $this->belongsTo(
            CashCategory::class,
            'cash_category_id',
            'id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CUENTA CONTABLE
    |--------------------------------------------------------------------------
    */

    public function accountingAccount()
    {
        return $this->belongsTo(
            AccountingAccount::class,
            'accounting_account_id',
            'id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PERÍODO CONTABLE
    |--------------------------------------------------------------------------
    */

    public function accountingPeriod()
    {
        return $this->belongsTo(
            AccountingPeriod::class,
            'accounting_period_id',
            'id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | MOVIMIENTO DE CAJA
    |--------------------------------------------------------------------------
    */

    public function cashMovement()
    {
        return $this->belongsTo(
            CashMovement::class,
            'cash_movement_id',
            'id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | ASIENTO CONTABLE
    |--------------------------------------------------------------------------
    */

    public function accountingEntry()
    {
        return $this->belongsTo(
            AccountingEntry::class,
            'accounting_entry_id',
            'id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    public function scopeIncome($query)
    {
        return $query->where('type', 'INGRESO');
    }

    public function scopeExpense($query)
    {
        return $query->where('type', 'EGRESO');
    }

    public function scopePosted($query)
    {
        return $query->where('status', 'POSTED');
    }

    public function scopeVoided($query)
    {
        return $query->where('status', 'VOIDED');
    }
}
