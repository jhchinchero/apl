<?php

namespace App\Domains\Contabilidad\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Domains\Empresa\Models\Company;
use App\Domains\Empresa\Models\Branch;
use App\Domains\Seguridad\Models\User;

class AccountingPeriod extends Model
{
    use HasFactory;

    protected $table = 'accounting_periods';

    /*
    |--------------------------------------------------------------------------
    | CAMPOS ASIGNABLES
    |--------------------------------------------------------------------------
    */
    protected $fillable = [
        'id_company',
        'branch_id',
        'type',
        'period',
        'start_date',
        'end_date',
        'status',
        'closed_at',
        'closed_by',
    ];

    /*
    |--------------------------------------------------------------------------
    | CASTS
    |--------------------------------------------------------------------------
    */
    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'closed_at'  => 'datetime',
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
    | USUARIO QUE CERRÓ
    |--------------------------------------------------------------------------
    */
    public function closedBy()
    {
        return $this->belongsTo(
            User::class,
            'closed_by',
            'id'
        );
    }
}