<?php

namespace App\Domains\Contabilidad\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Domains\Empresa\Models\Company;

class AccountingPaymentMapping extends Model
{
    use HasFactory;

    protected $table = 'accounting_payment_mappings';

    protected $fillable = [
        'id_company',
        'payment_method',
        'accounting_account_id',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(
            Company::class,
            'id_company',
            'id_company'
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

    public function scopeCompany($query, string $idCompany)
    {
        return $query->where(
            'id_company',
            $idCompany
        );
    }

    public function scopeActive($query)
    {
        return $query->where(
            'status',
            true
        );
    }

    public function scopePaymentMethod(
        $query,
        string $paymentMethod
    ) {
        return $query->where(
            'payment_method',
            strtoupper($paymentMethod)
        );
    }
}