<?php

namespace App\Domains\Contabilidad\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Domains\Empresa\Models\Company;
use App\Domains\Empresa\Models\Branch;
use App\Domains\Seguridad\Models\User;

class AccountingEntry extends Model
{
   use HasFactory;

    protected $table = 'accounting_entries';

    protected $fillable = [
        'id_company',
        'branch_id',
        'accounting_period_id',
        'code_entry',
        'date',
        'type',
        'description',
        'reference_type',
        'reference_id',
        'user_id',
        'status',
    ];

    protected $casts = [
        'date' => 'datetime',
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
    | PERÍODO
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
    | DETALLES
    |--------------------------------------------------------------------------
    */

    public function details()
    {
        return $this->hasMany(
            AccountingEntryDetail::class,
            'accounting_entry_id',
            'id'
        );
    }
    public function reference()
    {
        return $this->morphTo();
    }
}


/*
<?php

namespace App\Domains\Contabilidad\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Domains\Empresa\Models\Company;
use App\Domains\Empresa\Models\Branch;
use App\Domains\Seguridad\Models\User;

class AccountingEntry extends Model
{
    use HasFactory;

    protected $table = 'accounting_entries';

    protected $fillable = [
        'id_company',
        'branch_id',
        'accounting_period_id',
        'code_entry',
        'date',
        'type',
        'description',
        'reference_type',
        'reference_id',
        'user_id',
        'status',
    ];

    protected $casts = [
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

    public function accountingPeriod()
    {
        return $this->belongsTo(
            AccountingPeriod::class,
            'accounting_period_id',
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

    public function details()
    {
        return $this->hasMany(
            AccountingEntryDetail::class,
            'accounting_entry_id',
            'id'
        );
    }

    public function reference()
    {
        return $this->morphTo();
    }
}
*/