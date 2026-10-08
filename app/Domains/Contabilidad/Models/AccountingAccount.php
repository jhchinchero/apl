<?php

namespace App\Domains\Contabilidad\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Domains\Empresa\Models\Company;
use App\Domains\Contabilidad\Models\AccountingEntryDetail;
use App\Domains\Caja\Models\CashCategory;

class AccountingAccount extends Model
{
    use HasFactory;

    protected $table = 'accounting_accounts';

    protected $fillable = [
        'id_company',
        'account_type_id',
        'code',
        'name',
        'type',
        'level',
        'parent_id',
        'allows_entries',
        'status',
        'description'
    ];

    protected $casts = [
        'level' => 'integer',
        'allows_entries' => 'boolean',
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
    | CUENTA PADRE
    |--------------------------------------------------------------------------
    */

    public function parent()
    {
        return $this->belongsTo(
            self::class,
            'parent_id',
            'id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CUENTAS HIJAS
    |--------------------------------------------------------------------------
    */

    public function children()
    {
        return $this->hasMany(
            self::class,
            'parent_id',
            'id'
        )->orderBy('code');
    }

    /*
    |--------------------------------------------------------------------------
    | DETALLES CONTABLES
    |--------------------------------------------------------------------------
    */

    public function entryDetails()
    {
        return $this->hasMany(
            AccountingEntryDetail::class,
            'accounting_account_id',
            'id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CATEGORÍAS DE CAJA
    |--------------------------------------------------------------------------
    */

    public function cashCategories()
    {
        return $this->hasMany(
            CashCategory::class,
            'accounting_account_id',
            'id'
        );
    }
    /*
|--------------------------------------------------------------------------
| MAPEOS CONTABLES
|--------------------------------------------------------------------------
*/

/* public function mappings()
{
    return $this->hasMany(
        AccountingAccountMapping::class,
        'accounting_account_id',
        'id'
    );
} */
/*
|--------------------------------------------------------------------------
| TIPO DE CUENTA
|--------------------------------------------------------------------------
*/

public function accountType()
{
    return $this->belongsTo(
        AccountingAccountType::class,
        'account_type_id',
        'id'
    );
}

   /*
    |--------------------------------------------------------------------------
    | Scope empresa
    |--------------------------------------------------------------------------
    */

    public function scopeCompany($query, string $idCompany)
    {
        return $query->where(
            'id_company',
            $idCompany
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Scope activas
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
    | Scope movimiento
    |--------------------------------------------------------------------------
    */

    public function scopeMovement($query)
    {
        return $query->where(
            'allows_entries',
            true
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Scope agrupadoras
    |--------------------------------------------------------------------------
    */

    public function scopeGrouping($query)
    {
        return $query->where(
            'allows_entries',
            false
        );
    }
}