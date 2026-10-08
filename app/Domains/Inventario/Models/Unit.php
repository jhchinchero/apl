<?php

namespace App\Domains\Inventario\Models;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    //
    protected $table = 'units';

    protected $fillable = [
        'code_unit',
        'unit_name',
        'symbol',
        'description',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];
}
