<?php

namespace App\Domains\Seguridad\Models;

use Illuminate\Database\Eloquent\Model;

use App\Domains\Empresa\Models\Branch;
use App\Domains\Empresa\Models\PointOfSale;

class UserBranch extends Model
{
    protected $table = 'user_branches';

    protected $fillable = [
        'user_id',
        'branch_id',
        'point_of_sale_id',
    ];

    public function user()
    {
        return $this->belongsTo(
            User::class,
            'user_id'
        );
    }

    public function branch()
    {
        return $this->belongsTo(
            Branch::class,
            'branch_id'
        );
    }

    public function pointOfSale()
    {
        return $this->belongsTo(
            PointOfSale::class,
            'point_of_sale_id'
        );
    }
}
