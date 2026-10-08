<?php

namespace App\Domains\Cotizacion\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Domains\Empresa\Models\Company;
use App\Domains\Clientes\Models\Client;
use App\Domains\Seguridad\Models\User;


class Quotation extends Model
{
    use HasFactory;

    protected $table = 'quotations';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    /*
    |--------------------------------------------------------------------------
    | FILLABLE
    |--------------------------------------------------------------------------
    */

    protected $fillable = [

        'code_quotation',

        'id_company',

        'date',

        'client_id',

        'user_id',

        'total',

        'pdf_name',

        'client_name',

        'client_nit',

    ];

    /*
    |--------------------------------------------------------------------------
    | CASTS
    |--------------------------------------------------------------------------
    */

    protected $casts = [

        'date' => 'date',

        'total' => 'decimal:2',

    ];

    /*
    |--------------------------------------------------------------------------
    | COMPANY
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
    | CLIENT
    |--------------------------------------------------------------------------
    */

    public function client()
    {
        return $this->belongsTo(
            Client::class,
            'client_id',
            'id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | USER
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
    | DETAILS
    |--------------------------------------------------------------------------
    */

    public function details()
    {
        return $this->hasMany(
            QuotationDetail::class,
            'quotation_id',
            'id'
        );
    }
}