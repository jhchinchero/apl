<?php

namespace App\Domains\Ventas\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Domains\Empresa\Models\Company;

class SaleFiscalData extends Model
{
    use HasFactory;

    protected $table = 'sale_fiscal_data';

    protected $fillable = [
        'sale_id',
        'id_company',

        'invoice_number',
        'authorization_number',
        'cuf',
        'cufd',

        'branch_code',
        'pos_code',
        'invoice_type',

        'emission_date',

        'qr_code',
        'qr_url',

        'xml_url',
        'pdf_url',

        'status',
        'error_message',
    ];

    protected $casts = [
        'emission_date' => 'datetime',
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
    | VENTA
    |--------------------------------------------------------------------------
    */

    public function sale()
    {
        return $this->belongsTo(
            Sale::class,
            'sale_id',
            'id'
        );
    }
}