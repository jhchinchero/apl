<?php

namespace App\Domains\Seguridad\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Hash;
use App\Services\MonthlyResetService;
use App\Domains\Seguridad\Models\User;
use App\Domains\Empresa\Models\Company;
use App\Domains\Empresa\Models\Branch;

class AuthController extends Controller
{
    //
    /*
    |--------------------------------------------------------------------------
    | LOGIN
    |--------------------------------------------------------------------------
    */

    public function login(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | VALIDAR
        |--------------------------------------------------------------------------
        */

        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);


        /*
        |--------------------------------------------------------------------------
        | USUARIO
        |--------------------------------------------------------------------------
        */

        $user = User::where(
            'email',
            $request->email
        )->first();


        /*
        |--------------------------------------------------------------------------
        | CREDENCIALES
        |--------------------------------------------------------------------------
        */

        if (
            !$user ||
            !Hash::check(
                $request->password,
                $user->password
            )
        ) {

            return response()->json([
                'success' => false,
                'message' => 'Credenciales incorrectas.',
            ], 401);
        }


        /*
        |--------------------------------------------------------------------------
        | EMPRESA DEL USUARIO
        |--------------------------------------------------------------------------
        |
        | IMPORTANTE:
        | NO viene desde Vue.
        | Se obtiene directamente del usuario autenticado.
        |
        |--------------------------------------------------------------------------
        */

        $company = Company::where(
            'id_company',
            $user->id_company
        )->first();


        /*
        |--------------------------------------------------------------------------
        | LOGO
        |--------------------------------------------------------------------------
        */

        $logo = null;

        if (
            $company &&
            !empty($company->logo)
        ) {

            $logo = asset(
                'storage/' .
                    ltrim(
                        $company->logo,
                        '/'
                    )
            );
        }


        /*
        |--------------------------------------------------------------------------
        | SUCURSALES DEL USUARIO
        |--------------------------------------------------------------------------
        |
        | SOLAMENTE las sucursales asignadas al usuario.
        |
        |--------------------------------------------------------------------------
        */

        $branches = Branch::where(
            'id_company',
            $user->id_company
        )
            ->where('status', true)
            ->whereHas('users', function ($query) use ($user) {

                $query->where(
                    'users.id',
                    $user->id
                );
            })
            ->orderBy('id')
            ->get([
                'id',
                'code_branch',
                'branch_name',
            ]);


        /*
        |--------------------------------------------------------------------------
        | TOKEN
        |--------------------------------------------------------------------------
        */

        $token =
            $user
            ->createToken(
                'auth_token'
            )
            ->plainTextToken;


        /*
        |--------------------------------------------------------------------------
        | RESPUESTA
        |--------------------------------------------------------------------------
        */
        $dashboard = $user->email === 'disaat@disaat.com'
            ? 'system'
            : 'company';



        return response()->json([

            'success' => true,

            'message' => 'Inicio de sesión exitoso.',

            'data' => [

                /*
        |--------------------------------------------------------------------------
        | AUTENTICACIÓN
        |--------------------------------------------------------------------------
        */

                'access_token_v1' => $token,

                'token_type' => 'Bearer',


                /*
        |--------------------------------------------------------------------------
        | DASHBOARD
        |--------------------------------------------------------------------------
        */

                'dashboard' => $dashboard,


                /*
        |--------------------------------------------------------------------------
        | USUARIO
        |--------------------------------------------------------------------------
        */

                'user' => [

                    'name' => $user->name,

                    'email' => $user->email,

                    'role' => $user->role
                        ? $user->role->name
                        : null,

                ],


                /*
        |--------------------------------------------------------------------------
        | EMPRESA
        |--------------------------------------------------------------------------
        */

                'company' => $company
                    ? [

                        'business_name' =>
                        $company->business_name,

                        'logo' => $logo,

                    ]
                    : null,


                /*
        |--------------------------------------------------------------------------
        | SUCURSALES ASIGNADAS
        |--------------------------------------------------------------------------
        */

                'branches' => $branches,

            ],

        ], 200);
    }


    // Logout
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada correctamente.',
        ]);
    }
}
