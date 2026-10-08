<?php

namespace App\Domains\Clientes\Models;
use Illuminate\Database\Eloquent\Model; 
use Illuminate\Database\Eloquent\Factories\HasFactory; 
use Illuminate\Database\Eloquent\SoftDeletes; 
use App\Domains\Empresa\Models\Company;
use App\Domains\Cotizacion\Models\Quotation;

class Client extends Model
{
   use HasFactory, SoftDeletes; 
   protected $table = 'clients'; 
   protected $fillable = [ 'code_client', 'id_company', 'status', 'name', 'ciNit', 'email', 'phone', 'address', 'person_reference', 'balance', ]; 
   protected $casts = [ 'status' => 'boolean', 'balance' => 'decimal:2', ]; 
   public function company() { 
    return $this->belongsTo( Company::class, 'id_company', 'id_company' ); 
    }


    //

    public function sales()
{
    return $this->hasMany(
        \App\Domains\Ventas\Models\Sale::class,
        'client_id',
        'id'
    );
}

public function balanceMovements()
{
    return $this->hasMany(
        ClientBalanceMovement::class,
        'client_id',
        'id'
    );
}

public function saleReturns()
{
    return $this->hasMany(
        \App\Domains\Ventas\Models\SaleReturn::class,
        'client_id',
        'id'
    );
}
public function quotations()
{
    return $this->hasMany(
        Quotation::class,
        'client_id',
        'id'
    );
}
}
