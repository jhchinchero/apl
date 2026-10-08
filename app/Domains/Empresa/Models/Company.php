<?php

namespace App\Domains\Empresa\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Domains\Seguridad\Models\User;
use App\Domains\Empresa\Models\PointOfSale;
use App\Domains\Clientes\Models\Client;
use App\Domains\Caja\Models\CashMovement;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Domains\Facturacion\Models\SiatConfiguracion;
use App\Domains\Cotizacion\Models\Quotation;

class Company extends Model
{
    use HasFactory;


    protected $table = 'companies';


    protected $primaryKey = 'id_company';


    public $incrementing = false;


    protected $keyType = 'string';



    protected $fillable = [

        'id_company',

        'business_name',
        'razon_social',

        'tax_id',

        'website',

        'logo',

    ];

    public function users()
    {
        return $this->hasMany(
            User::class,
            'id_company',
            'id_company'
        );
    }
    //
    public function clients()
    {
        return $this->hasMany(Client::class, 'id_company', 'id_company');
    }

    public function pointOfSales()
    {
        return $this->hasMany(
            PointOfSale::class,
            'id_company',
            'id_company'
        );
    }
    public function cashMovements()
    {
        return $this->hasMany(
            CashMovement::class,
            'id_company',
            'id_company'
        );
    }
    public function quotations()
    {
        return $this->hasMany(
            Quotation::class,
            'id_company',
            'id_company'
        );
    }

/////


    /**
     * Configuración SIAT de la empresa (una por empresa)
     */
    public function siatConfiguracion(): HasOne
    {
        return $this->hasOne(
            SiatConfiguracion::class,
            'id_company',
            'id_company'
        );
    }

    /**
     * ============================================================
     * SCOPES
     * ============================================================
     */

    /**
     * Filtrar empresas activas
     */
    public function scopeActive($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Buscar por nombre de negocio
     */
    public function scopeByBusinessName($query, $businessName)
    {
        return $query->where('business_name', 'LIKE', "%{$businessName}%");
    }

    /**
     * Buscar por razón social
     */
    public function scopeByRazonSocial($query, $razonSocial)
    {
        return $query->where('razon_social', 'LIKE', "%{$razonSocial}%");
    }

    /**
     * Buscar por NIT/Tax ID
     */
    public function scopeByTaxId($query, $taxId)
    {
        return $query->where('tax_id', $taxId);
    }

    /**
     * ============================================================
     * ACCESSORS & MUTATORS
     * ============================================================
     */

    /**
     * Obtener nombre de pantalla (business_name o razon_social)
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->business_name ?? $this->razon_social;
    }

    /**
     * ============================================================
     * EVENTS
     * ============================================================
     */

    /**
     * Acciones antes de guardar
     */
    protected static function booting()
    {
        parent::booting();

        static::creating(function ($company) {
            // Asegurar que el ID es válido
            if (empty($company->id_company)) {
                $company->id_company = self::generateCompanyId();
            }
        });
    }

    /**
     * ============================================================
     * HELPERS
     * ============================================================
     */

    /**
     * Generar ID de empresa único
     */
    public static function generateCompanyId(): string
    {
        $count = self::count();
        return 'COMP-' . str_pad($count + 1, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Obtener configuración SIAT con relación cargada
     */
    public function getSiatConfig()
    {
        return $this->siatConfiguracion()
            ->with('company')
            ->first();
    }

    /**
     * Verificar si tiene configuración SIAT
     */
    public function hasSiatConfig(): bool
    {
        return $this->siatConfiguracion()->exists();
    }

    /**
     * Verificar si está activa en SIAT
     */
    public function isSiatActive(): bool
    {
        return $this->siatConfiguracion()
            ->where('activo', true)
            ->exists();
    }
}
