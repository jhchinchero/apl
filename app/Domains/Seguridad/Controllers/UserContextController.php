<?php

namespace App\Domains\Seguridad\Controllers;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Domains\Empresa\Models\Branch;
use App\Domains\Inventario\Models\Product;
use App\Domains\Inventario\Models\Category;
use App\Domains\Inventario\Models\Brand;
use App\Domains\Seguridad\Models\User;

class UserContextController extends Controller
{
    /**
     * ============================================================
     * CONTEXTO DEL USUARIO AUTENTICADO
     * ============================================================
     */
    public function index(Request $request)
    {
        try {

            /*
            |--------------------------------------------------------------------------
            | USUARIO AUTENTICADO
            |--------------------------------------------------------------------------
            */

            $authUser = Auth::user();

            if (!$authUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado.'
                ], 401);
            }


            /*
            |--------------------------------------------------------------------------
            | EMPRESA
            |--------------------------------------------------------------------------
            */

            $idCompany = $authUser->id_company;

            if (!$idCompany) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario no tiene una empresa asignada.'
                ], 422);
            }


            /*
            |--------------------------------------------------------------------------
            | USUARIO AUTENTICADO + SUS SUCURSALES
            |
            | Estas serán las sucursales que podrá utilizar como ORIGEN.
            |--------------------------------------------------------------------------
            */

            $user = User::query()
                ->where('id', $authUser->id)
                ->where('id_company', $idCompany)
                ->with([
                    'branches' => function ($query) use ($idCompany) {

                        $query
                            ->where('branches.id_company', $idCompany)
                            ->where('branches.status', true)
                            ->with([
                                'pointOfSales' => function ($query) {
                                    $query->where('status', true);
                                }
                            ]);
                    }
                ])
                ->first();


            /*
            |--------------------------------------------------------------------------
            | SUCURSALES ASIGNADAS AL USUARIO
            |
            | IMPORTANTE:
            |
            | Estas son SOLO las sucursales del usuario autenticado.
            | Se utilizan como sucursal de ORIGEN.
            |--------------------------------------------------------------------------
            */

            $branches = $user
                ? $user->branches
                : collect();


            /*
            |--------------------------------------------------------------------------
            | TODAS LAS SUCURSALES ACTIVAS DE LA EMPRESA
            |
            | Estas se utilizan para DESTINO.
            |
            | No importa si el usuario autenticado está asignado o no.
            |--------------------------------------------------------------------------
            */

            $allBranches = Branch::query()
                ->where('id_company', $idCompany)
                ->where('status', true)
                ->with([
                    'pointOfSales' => function ($query) {
                        $query->where('status', true);
                    }
                ])
                ->select([
                    'id',
                    'code_branch',
                    'id_company',
                    'branch_name',
                    'department',
                    'municipality',
                    'address',
                    'contact_name',
                    'phone',
                    'status'
                ])
                ->orderBy('branch_name')
                ->get();


            /*
            |--------------------------------------------------------------------------
            | USUARIOS ACTIVOS DE LA EMPRESA
            |
            | Cada usuario viene acompañado de sus sucursales asignadas.
            |
            | Vue utilizará esta información para determinar quién puede
            | recibir una transferencia en la sucursal destino.
            |--------------------------------------------------------------------------
            */

            $users = User::query()
                ->where('id_company', $idCompany)
                ->where('status', true)
                ->with([
                    'branches' => function ($query) use ($idCompany) {

                        $query
                            ->where('branches.id_company', $idCompany)
                            ->where('branches.status', true)
                            ->select([
                                'branches.id',
                                'branches.code_branch',
                                'branches.branch_name'
                            ]);
                    }
                ])
                ->select([
                    'id',
                    'name',
                    'email',
                    'status',
                    'id_company'
                ])
                ->orderBy('name')
                ->get();


            /*
            |--------------------------------------------------------------------------
            | ROL
            |--------------------------------------------------------------------------
            */

            $role = null;

            if (method_exists($authUser, 'role')) {
                $role = $authUser->role;
            }


            /*
            |--------------------------------------------------------------------------
            | EMPRESA
            |--------------------------------------------------------------------------
            */

            $company = null;

            if (method_exists($authUser, 'company')) {
                $company = $authUser->company;
            }


            /*
            |--------------------------------------------------------------------------
            | CATEGORÍAS
            |--------------------------------------------------------------------------
            */

            $categories = Category::query()
                ->where('id_company', $idCompany)
                ->where('status', true)
                ->select([
                    'id',
                    'code_category',
                    'category_name',
                    'status'
                ])
                ->orderBy('category_name')
                ->get();


            /*
            |--------------------------------------------------------------------------
            | MARCAS
            |--------------------------------------------------------------------------
            */

            $brands = Brand::query()
                ->where('id_company', $idCompany)
                ->where('status', true)
                ->select([
                    'id',
                    'code_brand',
                    'brand_name',
                    'status'
                ])
                ->orderBy('brand_name')
                ->get();


            /*
            |--------------------------------------------------------------------------
            | PRODUCTOS
            |
            | product_stock:
            |
            | id
            | id_company
            | branch_id
            | product_id
            | current_stock
            | status
            | price
            | location
            |--------------------------------------------------------------------------
            */

            $products = Product::query()
                ->where('id_company', $idCompany)
                ->where('status', true)
                ->with([
                    'category:id,category_name',

                    'brand:id,brand_name',

                    'stocks' => function ($query) use ($idCompany) {

                        $query
                            ->where('id_company', $idCompany)
                            ->select([
                                'id',
                                'id_company',
                                'branch_id',
                                'product_id',
                                'current_stock',
                                'status',
                                'price',
                                'location'
                            ]);
                    }
                ])
                ->select([
                    'id',
                    'code_product',
                    'id_company',
                    'name',
                    'model_product',
                    'brand_id',
                    'category_id',
                    'sale_price',
                    'purchase_price',
                    'unit_of_measure',
                    'status'
                ])
                ->orderBy('name')
                ->get();


            /*
            |--------------------------------------------------------------------------
            | CAJAS DEL USUARIO
            |--------------------------------------------------------------------------
            */

            $cashRegisters = collect();

            if (method_exists($authUser, 'cashRegisters')) {

                $cashRegisters = $authUser
                    ->cashRegisters()
                    ->where(
                        'cash_registers.id_company',
                        $idCompany
                    )
                    ->where(
                        'cash_registers.status',
                        true
                    )
                    ->get();
            }


            /*
            |--------------------------------------------------------------------------
            | RESPUESTA
            |--------------------------------------------------------------------------
            */

            return response()->json([

                'success' => true,

                'message' =>
                    'Contexto cargado correctamente.',

                'data' => [

                    /*
                    | Usuario autenticado
                    */
                    'user' => $user,

                    /*
                    | Rol
                    */
                    'role' => $role,

                    /*
                    | Empresa
                    */
                    'company' => $company,

                    /*
                    | Sucursales asignadas al usuario.
                    |
                    | SOLO PARA ORIGEN.
                    */
                    'branches' => $branches,

                    /*
                    | Todas las sucursales activas de la empresa.
                    |
                    | PARA DESTINO.
                    */
                    'allBranches' => $allBranches,

                    /*
                    | Usuarios activos de la empresa con sus sucursales.
                    */
                    'users' => $users,

                    /*
                    | Categorías
                    */
                    'categories' => $categories,

                    /*
                    | Marcas
                    */
                    'brands' => $brands,

                    /*
                    | Productos con stock por sucursal.
                    */
                    'products' => $products,

                    /*
                    | Cajas
                    */
                    'cashRegisters' => $cashRegisters,
                ]
            ]);

        } catch (\Throwable $e) {

            return response()->json([

                'success' => false,

                'message' =>
                    'Error al cargar el contexto del usuario.',

                'error' =>
                    $e->getMessage()

            ], 500);
        }
    }
}