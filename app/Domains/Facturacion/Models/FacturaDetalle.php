<?php

namespace App\Domains\Facturacion\Models;



use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\Facturacion\Models\FacturaCabecera;

class FacturaDetalle extends Model
{
    use HasFactory;

    protected $table = 'factura_detalle';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [

        // ================= FACTURA =================
        'factura_id',

        // ================= PRODUCTO / SERVICIO =================
        'actividad_economica',
        'codigo_producto_sin',
        'codigo_producto',
        'descripcion',
        'cantidad',
        'unidad_medida',
        'precio_unitario',
        'monto_descuento',
        'sub_total',

        // ================= SERIAL / DISPOSITIVO =================
        'numero_serie',
        'numero_imei',
    ];

    protected $casts = [

        'factura_id' => 'integer',

        'codigo_producto_sin' => 'integer',

        'cantidad' => 'decimal:2',

        'precio_unitario' => 'decimal:2',

        'monto_descuento' => 'decimal:2',

        'sub_total' => 'decimal:2',
    ];

    // ============================================================
    // FACTURA CABECERA
    // ============================================================

    public function factura(): BelongsTo
    {
        return $this->belongsTo(
            FacturaCabecera::class,
            'factura_id',
            'id'
        );
    }
}