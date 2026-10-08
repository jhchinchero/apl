<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

use App\Domains\Empresa\Models\Company;
use App\Domains\Seguridad\Models\Role;
use App\Domains\Seguridad\Models\Module;
use App\Domains\Seguridad\Models\Access;
use App\Domains\Seguridad\Models\User;

class RoleModuleUserAccess extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {


            /*
            |--------------------------------------------------------------------------
            | ROLES
            |--------------------------------------------------------------------------
            */

            $roles = [
                'admin',
                'supervisor',
                'vendedor',
                'contador',
                'cliente',
            ];


            foreach ($roles as $role) {

                Role::firstOrCreate([
                    'name' => $role
                ]);
            }



            /*
            |--------------------------------------------------------------------------
            | MÓDULOS
            |--------------------------------------------------------------------------
            */

            $modules = [

                // =========================
                // VENTAS
                // =========================
                [
                    'name' => 'ventas',
                    'description' => 'Gestión y registro de ventas.',
                ],
                [
                    'name' => 'devoluciones',
                    'description' => 'Gestión de devoluciones de productos.',
                ],
                [
                    'name' => 'cotizaciones',
                    'description' => 'Creación y gestión de cotizaciones.',
                ],

                // =========================
                // INVENTARIO
                // =========================
                [
                    'name' => 'inventario',
                    'description' => 'Control y gestión del inventario.',
                ],
                [
                    'name' => 'listas productos',
                    'description' => 'Consulta y administración de productos.',
                ],
                [
                    'name' => 'transferir productos',
                    'description' => 'Transferencia de productos entre sucursales.',
                ],
                [
                    'name' => 'aceptar transferencia',
                    'description' => 'Recepción y aceptación de transferencias.',
                ],

                // =========================
                // CLIENTES
                // =========================
                [
                    'name' => 'clientes',
                    'description' => 'Gestión y administración de clientes.',
                ],

                // =========================
                // CAJA
                // =========================
                [
                    'name' => 'Mi caja',
                    'description' => 'Consulta y gestión de la caja del usuario.',
                ],
                [
                    'name' => 'Registro de ingresos y egresos',
                    'description' => 'Registro y control de ingresos y egresos.',
                ],

                // =========================
                // CONTABILIDAD
                // =========================
                [
                    'name' => 'contabilidad libro diario',
                    'description' => 'Registro y consulta del libro diario.',
                ],
                [
                    'name' => 'contabilidad libro mayor',
                    'description' => 'Consulta y gestión del libro mayor.',
                ],
                [
                    'name' => 'contabilidad estados financieros',
                    'description' => 'Consulta de estados financieros.',
                ],
                [
                    'name' => 'contabilidad asientos contables',
                    'description' => 'Registro y gestión de asientos contables.',
                ],
                [
                    'name' => 'contabilidad periodos contables',
                    'description' => 'Administración de periodos contables.',
                ],

                // =========================
                // CONFIGURACIÓN
                // =========================
                [
                    'name' => 'configuracion catalogos',
                    'description' => 'Administración de catálogos del sistema.',
                ],
                [
                    'name' => 'configuracion de usuarios',
                    'description' => 'Gestión de usuarios del sistema.',
                ],
                [
                    'name' => 'configuracion de sucursales',
                    'description' => 'Administración de sucursales.',
                ],
                [
                    'name' => 'configuracion de asignar usuarios',
                    'description' => 'Asignación de usuarios a sucursales y funciones.',
                ],
                [
                    'name' => 'configuracion empresa',
                    'description' => 'Configuración y datos de la empresa.',
                ],

                // =========================
                // CONFIGURACIÓN CONTABLE
                // =========================
                [
                    'name' => 'configuracion contable plan de cuentas',
                    'description' => 'Administración del plan de cuentas contable.',
                ],
                [
                    'name' => 'configuracion contable categoria de cajas',
                    'description' => 'Gestión de categorías de cajas.',
                ],
                [
                    'name' => 'configuracion tipo de cuentas',
                    'description' => 'Administración de tipos de cuentas contables.',
                ],
                [
                    'name' => 'configuracion cuenta padre',
                    'description' => 'Gestión de cuentas padre contables.',
                ],
                [
                    'name' => 'configuracion disaat gestion empresa',
                    'description' => 'Configuración de la gestión empresarial de DISAAT.',
                ],
                [
                    'name' => 'configuracion unidad de medida',
                    'description' => 'Administración de unidades de medida.',
                ],
                 [
                    'name' => 'punto de venta',
                    'description' => 'punto deventas',
                ],
                  [
                    'name' => 'facturacion',
                    'description' => 'facturacion',
                ],
            ];

            foreach ($modules as $module) {

                Module::firstOrCreate(
                    [
                        'name' => $module['name']
                    ],
                    [
                        'description' => $module['description']
                    ]
                );
            }




            /*
            |--------------------------------------------------------------------------
            | EMPRESA
            |--------------------------------------------------------------------------
            */

            $company = Company::firstOrCreate(
                [
                    'tax_id' => '123456789'
                ],
                [
                    'id_company' => 'EMP-00001',
                    'business_name' => 'My Company',
                    'razon_social'=>'My comp',
                    'website' => 'https://www.mycompany.com',
                    'logo' => null,
                ]
            );





            /*
            |--------------------------------------------------------------------------
            | USUARIOS
            |--------------------------------------------------------------------------
            */

            $users = [

                [
                    'name' => 'Administrador',
                    'email' => 'disaat@disaat.com',
                    'role' => 'admin',
                ],

                [
                    'name' => 'Supervisor',
                    'email' => 'supervisor@disaat.com',
                    'role' => 'supervisor',
                ],

                [
                    'name' => 'Vendedor',
                    'email' => 'vendedor@disaat.com',
                    'role' => 'vendedor',
                ],

                [
                    'name' => 'Contador',
                    'email' => 'contador@disaat.com',
                    'role' => 'contador',
                ],

                [
                    'name' => 'Cliente',
                    'email' => 'cliente@email.com',
                    'role' => 'cliente',
                ],

            ];





            foreach ($users as $data) {


                $role = Role::where('name', $data['role'])
                    ->firstOrFail();



                User::updateOrCreate(

                    [
                        'email' => $data['email']
                    ],


                    [

                        'status' => true,

                        'name' => $data['name'],

                        'password' => Hash::make('12345678'),

                        'phone' => null,

                        'address' => null,

                        'reference_person' => null,


                        // FK ROLE
                        'role_id' => $role->id,


                        // FK COMPANY STRING
                        // Ejemplo: MYC-00001
                        'id_company' => $company->id_company,

                    ]

                );
            }






            /*
            |--------------------------------------------------------------------------
            | ACCESOS
            |--------------------------------------------------------------------------
            */


            $modules = Module::all();



            $users = User::with('role')->get();



            foreach ($users as $user) {


                $isAdmin = $user->role->name === 'admin';



                foreach ($modules as $module) {


                    Access::updateOrCreate(

                        [
                             'id_company' => $company->id_company,

                            'user_id' => $user->id,

                            'module_id' => $module->id,

                        ],


                        [

                            'can_view' => $isAdmin,

                            'can_create' => $isAdmin,

                            'can_edit' => $isAdmin,

                            'can_delete' => $isAdmin,

                        ]

                    );
                }
            }
        });
    }
}
