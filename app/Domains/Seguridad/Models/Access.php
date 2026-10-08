<?php

namespace App\Domains\Seguridad\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Domains\Seguridad\Models\User;
use App\Domains\Seguridad\Models\Module;
use App\Domains\Empresa\Models\Branch;
use App\Domains\Empresa\Models\Company;


class Access extends Model
{
    use HasFactory;

    protected $table = 'accesses';

    protected $fillable = [
        'id_company',
        'module_id',
        'user_id',
        'branch_id',
        'can_view',
        'can_create',
        'can_edit',
        'can_delete',
    ];

    protected $casts = [
        'can_view' => 'boolean',
        'can_create' => 'boolean',
        'can_edit' => 'boolean',
        'can_delete' => 'boolean',
    ];

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
    | MODULO
    |--------------------------------------------------------------------------
    */

    public function module()
    {
        return $this->belongsTo(
            Module::class,
            'module_id',
            'id'
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
     public function company()
    {
        return $this->belongsTo(
            Company::class,
            'id_company',
            'id_company'
        );
    }
}