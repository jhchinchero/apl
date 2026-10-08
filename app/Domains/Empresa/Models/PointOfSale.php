<?php

namespace App\Domains\Empresa\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

use App\Domains\Empresa\Models\Company;
use App\Domains\Empresa\Models\Branch;
use App\Domains\Caja\Models\CashRegister;

use App\Domains\Facturacion\Models\Cuis;
use App\Domains\Facturacion\Models\Cufd;
use App\Domains\Facturacion\Models\FacturaCabecera;

class PointOfSale extends Model
{
    use HasFactory;

    protected $table = 'point_of_sales';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'id_company',
        'branch_id',
        'codigo',
        'name',
        'status',
        'codigo'
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    /**
     * Empresa
     */
    public function company()
    {
        return $this->belongsTo(
            Company::class,
            'id_company',
            'id_company'
        );
    }

    /**
     * Sucursal
     */
    public function branch()
    {
        return $this->belongsTo(
            Branch::class,
            'branch_id',
            'id'
        );
    }
    public function cashRegisters()
    {
        return $this->hasMany(
            CashRegister::class,
            'point_of_sale_id',
            'id'
        );
    }
    //facturacion

    public function cuis(): HasMany
{
    return $this->hasMany(
        Cuis::class,
        'punto_venta_id'
    );
}

public function cufds(): HasMany
{
    return $this->hasMany(
        Cufd::class,
        'punto_venta_id'
    );
}

   /**
     * Facturas
     */
    public function facturas(): HasMany
{
    return $this->hasMany(
        FacturaCabecera::class,
        'punto_venta_id',
        'id'
    );
}
}



