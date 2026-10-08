<?php

namespace App\Domains\Facturacion\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domains\Empresa\Models\Company;
use App\Domains\Empresa\Models\PointOfSale;

class FacturaCabecera extends Model
{
    use HasFactory;

    protected $table = 'factura_cabecera';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'id_company',

        // ================= ESTADO =================
        'estado',

        // ================= SIAT EMISOR =================
        'nit_emisor',
        'razon_social_emisor',
        'municipio',
        'telefono',

        // ================= FACTURA =================
        'numero_factura',
        'cuf',
        'cufd',
        'codigo_sucursal',
        'direccion',
        'codigo_punto_venta',
        'fecha_emision',

        // ================= CLIENTE =================
        'nombre_razon_social',
        'codigo_tipo_documento_identidad',
        'numero_documento',
        'complemento',
        'codigo_cliente',

        // ================= PAGO =================
        'codigo_metodo_pago',
        'numero_tarjeta',

        // ================= MONTO =================
        'monto_total',
        'monto_total_sujeto_iva',
        'codigo_moneda',
        'tipo_cambio',
        'monto_total_moneda',
        'monto_gift_card',
        'descuento_adicional',

        // ================= SIAT CONTROL =================
        'codigo_excepcion',
        'cafc',

        // ================= OTROS =================
        'leyenda',
        'usuario',
        'codigo_documento_sector',
        'pdf_path',

        // ================= PUNTO DE VENTA =================
        'punto_venta_id',
    ];

    protected $casts = [

        'estado' => 'integer',

        'numero_factura' => 'integer',

        'fecha_emision' => 'datetime',

        'codigo_tipo_documento_identidad' => 'integer',

        'codigo_metodo_pago' => 'integer',

        'monto_total' => 'decimal:2',

        'monto_total_sujeto_iva' => 'decimal:2',

        'codigo_moneda' => 'integer',

        'tipo_cambio' => 'decimal:2',

        'monto_total_moneda' => 'decimal:2',

        'monto_gift_card' => 'decimal:2',

        'descuento_adicional' => 'decimal:2',

        'codigo_excepcion' => 'integer',

        'codigo_documento_sector' => 'integer',

        'punto_venta_id' => 'integer',
    ];

    // ============================================================
    // PUNTO DE VENTA
    // ============================================================

    public function puntoVenta(): BelongsTo
    {
        return $this->belongsTo(
            PointOfSale::class,
            'punto_venta_id',
            'id'
        );
    }

    // ============================================================
    // DETALLES
    // ============================================================

    public function detalles(): HasMany
    {
        return $this->hasMany(
            FacturaDetalle::class,
            'factura_id',
            'id'
        );
    }
    public function company(): BelongsTo
{
    return $this->belongsTo(
        Company::class,
        'id_company',
        'id_company'
    );
}
}