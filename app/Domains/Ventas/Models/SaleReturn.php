<?php

namespace App\Domains\Ventas\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Domains\Empresa\Models\Company;
use App\Domains\Empresa\Models\Branch;
use App\Domains\Clientes\Models\Client;
use App\Domains\Seguridad\Models\User;
use App\Domains\Caja\Models\CashRegisterSession;
use App\Domains\Caja\Models\CashRegister;

class SaleReturn extends Model
{
    use HasFactory;

    protected $table = 'sale_returns';

    protected $fillable = [
        'code_return',
        'id_company',
        'sale_id',
        'client_id',
        'branch_id',
        'user_id',
        'cash_register_id',
        'cash_register_session_id',
        'refund_type',
        'refund_method',
        'reason',
        'total_amount',
        'status',
        'date',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'date' => 'datetime',
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

    public function sale()
    {
        return $this->belongsTo(
            Sale::class,
            'sale_id',
            'id'
        );
    }

    public function client()
    {
        return $this->belongsTo(
            Client::class,
            'client_id',
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

    public function items()
    {
        return $this->hasMany(
            SaleReturnItem::class,
            'sale_return_id',
            'id'
        );
    }
}