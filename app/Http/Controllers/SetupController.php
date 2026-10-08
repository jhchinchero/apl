<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

use App\Domains\Empresa\Models\Company;
use App\Domains\Seguridad\Models\User;
use App\Domains\Seguridad\Models\Access;


class SetupController extends Controller
{


    public function store(Request $request)
    {


        DB::beginTransaction();


        try {


            /*
            |--------------------------------------------------------------------------
            | VALIDAR DATOS - MÁXIMA SEGURIDAD
            |--------------------------------------------------------------------------
            */


            $request->validate(

                [

                    'company.business_name' => 'required|string|max:255',
                    'company.razon_social' => 'required|string|max:255',

                    'company.tax_id' => 'required|unique:companies,tax_id|regex:/^[0-9\-]+$/',

                    'company.website' => 'nullable|max:255',

                    'user.name' => 'required|string|max:255',

                    'user.email' => 'required|email|unique:users,email|max:255',

                    'user.phone' => 'nullable|regex:/^[0-9\+\-\(\)\s]+$/',

                    'user.password' => [
                        'required',
                        'confirmed',
                        /* Password::min(8)
                            ->mixedCase()
                            ->numbers()
                            ->symbols() */
                    ],

                    'user.role_id' => 'required|integer|exists:roles,id',

                    'permissions' => 'required|array|min:1',

                    'permissions.*.module_id' => 'required|integer|exists:modules,id',

                    'permissions.*.can_view' => 'nullable|boolean',

                    'permissions.*.can_create' => 'nullable|boolean',

                    'permissions.*.can_edit' => 'nullable|boolean',

                    'permissions.*.can_delete' => 'nullable|boolean'

                ],


                [

                    'company.business_name.required' => 'La razón social es obligatoria.',
                    'company.razon_social.required' => 'La razón social es obligatoria.',

                    'company.business_name.max' => 'La razón social no puede exceder 255 caracteres.',

                    'company.tax_id.required' => 'El NIT es obligatorio.',

                    'company.tax_id.unique' => 'Ya existe una empresa registrada con este NIT.',

                    'company.tax_id.regex' => 'El NIT debe contener solo números y guiones.',

                    'user.name.required' => 'El nombre del usuario es obligatorio.',

                    'user.name.max' => 'El nombre no puede exceder 255 caracteres.',

                    'user.email.required' => 'El correo es obligatorio.',

                    'user.email.email' => 'El correo no tiene un formato válido.',

                    'user.email.unique' => 'Ya existe un usuario registrado con este correo.',

                    'user.email.max' => 'El correo no puede exceder 255 caracteres.',

                    'user.phone.regex' => 'El teléfono contiene caracteres inválidos.',

                    'user.password.required' => 'La contraseña es obligatoria.',

                    'user.password.confirmed' => 'Las contraseñas no coinciden.',

                    'user.password.min' => 'La contraseña debe tener mínimo 8 caracteres.',

                    'user.password.mixed_case' => 'La contraseña debe contener mayúsculas y minúsculas.',

                    'user.password.numbers' => 'La contraseña debe contener números.',

                    'user.password.symbols' => 'La contraseña debe contener caracteres especiales.',

                    'user.role_id.required' => 'El rol del usuario es obligatorio.',

                    'user.role_id.exists' => 'El rol seleccionado no es válido.',

                    'permissions.required' => 'Debe asignar permisos a los módulos.',

                    'permissions.min' => 'Debe asignar al menos un permiso.',

                    'permissions.*.module_id.required' => 'El módulo es obligatorio.',

                    'permissions.*.module_id.exists' => 'El módulo seleccionado no es válido.'

                ]

            );




            /*
            |--------------------------------------------------------------------------
            | GENERAR id_company CORRELATIVO
            |--------------------------------------------------------------------------
            | Formato: EMP-00001, EMP-00002, EMP-00003, etc.
            | Obtiene el último número y suma 1
            |--------------------------------------------------------------------------
            */
            
            // Obtener el último id_company de la tabla
            $lastCompany = Company::orderBy('id_company', 'desc')->first();
            
            if ($lastCompany) {
                // Extraer el número del último ID (ej: EMP-00001 -> 00001 -> 1)
                $lastNumber = (int) substr($lastCompany->id_company, 4); // Quita "EMP-"
                $newNumber = $lastNumber + 1;
            } else {
                // Si no hay empresas, comenzar en 1
                $newNumber = 1;
            }
            
            // Formatear con ceros a la izquierda (5 dígitos)
            $companyId = 'EMP-' . str_pad($newNumber, 5, '0', STR_PAD_LEFT);
            // Resultado: EMP-00001, EMP-00002, etc.




            /*
            |--------------------------------------------------------------------------
            | CREAR EMPRESA
            |--------------------------------------------------------------------------
            */
            $company = Company::create([

                'id_company' => $companyId,

                'business_name' => $request->input('company.business_name'),
                'razon_social' => $request->input('company.razon_social'),

                'tax_id' => $request->input('company.tax_id'),

                'website' => $request->input('company.website'),

            ]);




            /*
            |--------------------------------------------------------------------------
            | CREAR USUARIO ADMINISTRADOR
            |--------------------------------------------------------------------------
            */
            $user = User::create([
                'name' => $request->input('user.name'),
                'email' => $request->input('user.email'),
                'phone' => $request->input('user.phone'),
                'password' => Hash::make($request->input('user.password')),
                'role_id' => $request->input('user.role_id'),
                'id_company' => $companyId,
                'status' => true
            ]);

            /*
            |--------------------------------------------------------------------------
            | CREAR PERMISOS
            |--------------------------------------------------------------------------
            */

            foreach ($request->input('permissions') as $permission) {
                  // No crear acceso al módulo 25
    if ((int) $permission['module_id'] === 25) {
        continue;
    }


                Access::create([
                    'id_company'=>$companyId,

                    'module_id' => $permission['module_id'],

                    'user_id' => $user->id,

                    'can_view' => $permission['can_view'] ?? false,

                    'can_create' => $permission['can_create'] ?? false,

                    'can_edit' => $permission['can_edit'] ?? false,

                    'can_delete' => $permission['can_delete'] ?? false,
                    

                ]);

            }

            DB::commit();

            return response()->json([

                'message' => 'Configuración creada correctamente',

                'company' => $company,

                'user' => $user

            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {

            DB::rollBack();

            return response()->json([

                'success' => false,

                'message' => 'Error de validación',

                'errors' => $e->errors()

            ], 422);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([

                'success' => false,

                'message' => 'Error al crear la configuración',

                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'

            ], 500);

        }

    }


}