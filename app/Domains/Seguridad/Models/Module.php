<?php

namespace App\Domains\Seguridad\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Domains\Seguridad\Models\Access;
class Module extends Model
{
    //
    use HasFactory;

    protected $fillable = ['name', 'description', 'status'];

    public function accesses()
    {
        return $this->hasMany(Access::class);
    }
    
}
