<?php

namespace App\Domains\Caja\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Domains\Empresa\Models\Company;
use App\Domains\Empresa\Models\Branch;
use App\Domains\Seguridad\Models\User;

class CashRegisterSession extends Model
{
    use HasFactory;

    protected $table = 'cash_register_sessions';

    protected $fillable = [
        'id_company',
        'branch_id', //nuevo
        'cash_register_id',
        'user_id',

        'opening_amount',
        'expected_amount',
        'closing_amount', //counted_amount
        'difference',

        'opened_at',
        'closed_at',

        'status',

        'opening_note',
        'closing_note',

    ];

    protected $casts = [
        'opening_amount' => 'decimal:2',
        'expected_amount' => 'decimal:2',
        'closing_amount' => 'decimal:2',
        'difference' => 'decimal:2',

        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(
            Company::class,
            'id_company',
            'id_company'
        );
    }

    public function cashRegister()
    {
        return $this->belongsTo(
            CashRegister::class,
            'cash_register_id',
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

    public function movements()
    {
        return $this->hasMany(
            CashMovement::class,
            'cash_register_session_id',
            'id'
        );
    }

    public function salePayments()
    {
        return $this->hasMany(
            \App\Domains\Ventas\Models\SalePayment::class,
            'cash_register_session_id',
            'id'
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
}
