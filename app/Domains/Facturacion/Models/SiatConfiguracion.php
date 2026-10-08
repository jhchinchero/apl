<?php

namespace App\Domains\Facturacion\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\Empresa\Models\Company;

class SiatConfiguracion extends Model
{
    protected $table = 'siat_configuraciones';

    protected $fillable = [
        'id_company',
        'codigo_ambiente',
        'codigo_modalidad',
        'codigo_sistema',
        'nit',
        'codigo_emision',
        'tipo_factura_documento',
        'codigo_documento_sector',
        'token_delegado',
        'activo',
    ];

    protected $casts = [
        'codigo_ambiente' => 'integer',
        'codigo_modalidad' => 'integer',
        'codigo_sistema' => 'string',
        'nit' => 'integer',
        'codigo_emision' => 'integer',
        'tipo_factura_documento' => 'integer',
        'codigo_documento_sector' => 'integer',

        // El token se guarda cifrado en la BD
        'token_delegado' => 'encrypted',

        'activo' => 'boolean',
    ];

    /**
     * Empresa a la que pertenece la configuración SIAT.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(
            Company::class,
            'id_company',
            'id_company'
        );
    }
}
