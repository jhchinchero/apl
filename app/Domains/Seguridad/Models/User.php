<?php

namespace App\Domains\Seguridad\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Domains\Empresa\Models\Company;
use App\Domains\Seguridad\Models\Role;
use App\Domains\Seguridad\Models\Access;
use App\Domains\Empresa\Models\Branch;
use App\DOmains\Caja\Models\CashRegister;
use App\Domains\Caja\Models\CashRegisterSession;
use App\Domains\Caja\Models\CashMovement;
use App\Domains\Inventario\Models\ProductTransfer;
use App\Domains\Cotizacion\Models\Quotation;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'status',
        'name',
        'email',
        'password',
        'phone',
        'address',
        'reference_person',
        'cash_register_session_id',
        'role_id',
        'id_company',

    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /*  public function accesses()
    {
        return $this->hasMany(Access::class);
    } */
    public function accesses()
    {
        return $this->hasMany(
            Access::class,
            'user_id',
            'id'
        );
    }
    public function modules()
    {
        return $this->belongsToMany(
            Module::class,
            'accesses',
            'user_id',
            'module_id'
        )->withPivot([
            'branch_id',
            'can_view',
            'can_create',
            'can_edit',
            'can_delete'
        ]);
    }
    public function company()
    {
        return $this->belongsTo(
            Company::class,
            'id_company',
            'id_company'
        );
    }
    /* public function branch()
    {
        return $this->belongsTo(
            Branch::class,
            'branch_id',
            'id_branch'
        );
    } */
    /*  public function branches()
    {
        return $this->belongsToMany(
            Branch::class,
            'user_branches',
            'user_id',
            'branch_id'
        )
            ->withPivot('point_of_sale_id')
            ->withTimestamps();
    } */
    public function branches()
    {
        return $this->belongsToMany(
            Branch::class,
            'user_branches',
            'user_id',
            'branch_id'
        )
            ->withPivot('point_of_sale_id')
            ->withTimestamps();
    }
    /*
|--------------------------------------------------------------------------
| CAJAS ASIGNADAS
|--------------------------------------------------------------------------
*/

    public function cashRegisters()
    {
        return $this->belongsToMany(
            CashRegister::class,
            'cash_register_user',
            'user_id',
            'cash_register_id'
        )
            ->withPivot([
                'id_company',
                'status',
            ])
            ->withTimestamps();
    }

    /*
|--------------------------------------------------------------------------
| SESIONES DE CAJA
|--------------------------------------------------------------------------
*/

    public function cashRegisterSessions()
    {
        return $this->hasMany(
            CashRegisterSession::class,
            'user_id',
            'id'
        );
    }
    public function cashMovements()
    {
        return $this->hasMany(
            CashMovement::class,
            'user_id',
            'id'
        );
    }
    /*
|--------------------------------------------------------------------------
| TRANSFERENCIAS REALIZADAS
|--------------------------------------------------------------------------
*/

    public function productTransfers()
    {
        return $this->hasMany(
            ProductTransfer::class,
            'user_id',
            'id'
        );
    }
    public function quotations()
{
    return $this->hasMany(
        Quotation::class,
        'user_id',
        'id'
    );
}
}
