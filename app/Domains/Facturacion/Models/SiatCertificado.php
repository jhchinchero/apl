<?php

namespace App\Domains\Facturacion\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\Empresa\Models\Company;

class SiatCertificado extends Model
{
    protected $table = 'siat_certificados';

    protected $fillable = [
        'id_company',
        'certificado_path',
        'llave_privada_path',
        'password',
        'nombre',
        'serial',
        'fecha_inicio',
        'fecha_vencimiento',
        'activo',
    ];

    protected $casts = [
        'password' => 'encrypted',

        'fecha_inicio' => 'datetime',
        'fecha_vencimiento' => 'datetime',

        'activo' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(
            Company::class,
            'id_company',
            'id_company'
        );
    }
}