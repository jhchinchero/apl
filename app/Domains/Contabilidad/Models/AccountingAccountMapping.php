<?php

namespace App\Domains\Contabilidad\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Domains\Empresa\Models\Company;

class AccountingAccountMapping extends Model
{
    use HasFactory;

    protected $table = 'accounting_account_mappings';

    protected $fillable = [
        'id_company',
        'key',
        'name',
        'accounting_account_id',
        'description',
        'status',
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
    | SCOPE EMPRESA
    |--------------------------------------------------------------------------
    */

    public function scopeCompany(
        $query,
        string $idCompany
    ) {
        return $query->where(
            'id_company',
            $idCompany
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPE ACTIVOS
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query->where(
            'status',
            true
        );
    }

    /*
    |--------------------------------------------------------------------------
    | BUSCAR POR KEY
    |--------------------------------------------------------------------------
    */

    public function scopeKey(
        $query,
        string $key
    ) {
        return $query->where(
            'key',
            $key
        );
    }
}