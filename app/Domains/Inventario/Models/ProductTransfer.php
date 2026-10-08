<?php

namespace App\Domains\Inventario\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Domains\Empresa\Models\Company;
use App\Domains\Empresa\Models\Branch;
use App\Domains\Seguridad\Models\User;

class ProductTransfer extends Model
{
    use HasFactory;

    protected $table = 'product_transfers';

    protected $fillable = [
        'id_company',
        'user_id',
        'received_by_user_id',
        'origin_branch_id',
        'destination_branch_id',
        'code_transfer',
        'status',
        'observation',
        'sent_at',
        'received_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'received_at' => 'datetime',
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
    | USUARIO QUE ENVÍA
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
    | USUARIO QUE RECIBE / PROCESA
    |--------------------------------------------------------------------------
    */

    public function receivedByUser()
    {
        return $this->belongsTo(
            User::class,
            'received_by_user_id',
            'id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SUCURSAL ORIGEN
    |--------------------------------------------------------------------------
    */

    public function originBranch()
    {
        return $this->belongsTo(
            Branch::class,
            'origin_branch_id',
            'id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SUCURSAL DESTINO
    |--------------------------------------------------------------------------
    */

    public function destinationBranch()
    {
        return $this->belongsTo(
            Branch::class,
            'destination_branch_id',
            'id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | ITEMS
    |--------------------------------------------------------------------------
    */

    public function items()
    {
        return $this->hasMany(
            ProductTransferItem::class,
            'product_transfer_id',
            'id'
        );
    }
}