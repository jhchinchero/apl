<?php

namespace App\Domains\Seguridad\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Domains\Seguridad\Models\Role;

class RolesController extends Controller
{
     public function index()
    {

        try {


            $roles = Role::select(
                    'id',
                    'name'
                )->get();



            return response()->json([

                'success'=>true,

                'data'=>$roles

            ],200);



        } catch(\Exception $e){


            return response()->json([

                'success'=>false,

                'message'=>'Error al obtener roles',

                'error'=>$e->getMessage()

            ],500);


        }

    }
}
