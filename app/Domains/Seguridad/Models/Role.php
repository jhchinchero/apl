<?php

namespace App\Domains\Seguridad\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Domains\Seguridad\Models\Access;
use App\Domains\Seguridad\Models\User;
class Role extends Model
{
    //
     //
    use HasFactory;

    protected $fillable = ['name'];

   public function accesses()
    {
        return $this->hasMany(Access::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    } 
}
