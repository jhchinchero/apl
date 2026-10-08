<?php

namespace App\Domains\Ventas\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Domains\Ventas\Models\Sale;
use App\Domains\Caja\Models\CashRegister;
use App\Domains\Caja\Models\CashRegisterSession;
use App\Domains\Empresa\Models\Company;

class SalePayment extends Model
{
    use HasFactory;

    protected $table = 'sale_payments';

    protected $fillable = [
        'id_company',
        'sale_id',
        'cash_register_id',
        'cash_register_session_id',
        'payment_method',
        'amount',
        'reference',
        'observation',
        'is_collected'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_collected' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(
            Company::class,
            'id_company',
            'id_company'
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

    public function cashRegister()
    {
        return $this->belongsTo(
            CashRegister::class,
            'cash_register_id',
            'id'
        );
    }

    public function cashRegisterSession()
    {
        return $this->belongsTo(
            CashRegisterSession::class,
            'cash_register_session_id',
            'id'
        );
    }
}