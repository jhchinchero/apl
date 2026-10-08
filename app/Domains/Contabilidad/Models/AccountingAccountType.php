<?php

namespace App\Domains\Contabilidad\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingAccountType extends Model
{
    use HasFactory;

    protected $table = 'accounting_account_types';

    protected $fillable = [
        'code',
        'name',
        'nature',
        'status',
        'description',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    /**
     * Cuentas contables pertenecientes a este tipo.
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(
            AccountingAccount::class,
            'account_type_id'
        );
    }

    /**
     * Scope para tipos activos.
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }
}