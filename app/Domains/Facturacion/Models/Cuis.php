<?php

namespace App\Domains\Facturacion\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Domains\Empresa\Models\PointOfSale;

class Cuis extends Model
{
    protected $table = 'cuis';

    protected $fillable = [
        'codigo',
        'fecha_solicitada',
        'fecha_vigencia',
        'vigente',
        'punto_venta_id',
    ];

    protected $casts = [
        'fecha_solicitada' => 'datetime',
        'fecha_vigencia'   => 'datetime',
        'vigente'          => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | PUNTO DE VENTA
    |--------------------------------------------------------------------------
    */

    public function pointOfSale(): BelongsTo
    {
        return $this->belongsTo(
            PointOfSale::class,
            'punto_venta_id'
        );
    }
}