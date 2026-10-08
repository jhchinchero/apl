<?php
namespace App\Domains\Caja\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Domains\Empresa\Models\Company;
use App\Domains\Contabilidad\Models\AccountingAccount;
use App\Domains\Caja\Models\CashMovement;

class CashCategory extends Model
{
    use HasFactory;

    protected $table = 'cash_categories';

    protected $fillable = [
        'id_company',
        'code',
        'name',
        'type',
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

    public function movements()
    {
        return $this->hasMany(
            CashMovement::class,
            'cash_category_id',
            'id'
        );
    }
}