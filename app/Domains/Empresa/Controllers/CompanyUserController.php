<?php

namespace App\Domains\Empresa\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;


use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Domains\Empresa\Models\Company;
use App\Domains\Seguridad\Models\Access; 
class CompanyUserController extends Controller
{
    public function show()
    {
        try {

            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado.'
                ], 401);
            }

            if (!$user->id_company) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario no tiene una empresa asignada.'
                ], 404);
            }

            $company = Company::where(
                'id_company',
                $user->id_company
            )->first();

            if (!$company) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró la empresa.'
                ], 404);
            }
            $access = Access::where('user_id', $user->id)
                ->where('module_id', 20) //modulo 20 empresa edit
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Información de la empresa obtenida correctamente.',
                'data' => [
                    'id_company' => $company->id_company,
                    'business_name' => $company->business_name,
                    'tax_id' => $company->tax_id,
                    'razon_social'=>$company->razon_social,
                    'website' => $company->website,

                    'logo' => $company->logo
                        ? asset('storage/' . $company->logo)
                        : null,
                    'access'=>$access
                ]
            ], 200);
        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la empresa.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Actualizar empresa.
     */
    public function update(Request $request)
    {
        $newLogo = null;

        try {

            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado.'
                ], 401);
            }

            if (!$user->id_company) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario no tiene una empresa asignada.'
                ], 404);
            }


            /*
            |--------------------------------------------------------------------------
            | Buscar solamente la empresa del usuario
            |--------------------------------------------------------------------------
            */

            $company = Company::where(
                'id_company',
                $user->id_company
            )->first();

            if (!$company) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró la empresa.'
                ], 404);
            }


            /*
            |--------------------------------------------------------------------------
            | Validación
            |--------------------------------------------------------------------------
            */

            $validated = $request->validate([

                'business_name' => [
                    'required',
                    'string',
                    'max:255'
                ],
                'razon_social' => [
                    'string',
                    'max:255'
                ],

                'tax_id' => [
                    'required',
                    'string',
                    'max:50',
                    'unique:companies,tax_id,' .
                        $company->id_company .
                        ',id_company'
                ],

                'website' => [
                    'nullable',
                    'string',
                    'max:255'
                ],

                'logo' => [
                    'nullable',
                    'image',
                    'mimes:jpg,jpeg,png,webp',
                    'max:5120'
                ],

            ]);


            /*
            |--------------------------------------------------------------------------
            | Guardar logo anterior
            |--------------------------------------------------------------------------
            */

            $oldLogo = $company->logo;


            /*
            |--------------------------------------------------------------------------
            | Si se seleccionó un nuevo logo
            |--------------------------------------------------------------------------
            */

            if ($request->hasFile('logo')) {

                $file = $request->file('logo');

                /*
                | Nombre limpio de la empresa
                */

                $companyName = Str::slug(
                    $company->business_name,
                    '_'
                );

                /*
                | Hash aleatorio
                */

                $hash = Str::random(12);

                /*
                | Extensión
                */

                $extension = strtolower(
                    $file->getClientOriginalExtension()
                );

                /*
                | Nombre final
                |
                | logo_NombreEmpresa_HASH.ext
                */

                $fileName =
                    'logo_' .
                    $companyName .
                    '_' .
                    $hash .
                    '.' .
                    $extension;


                /*
                |--------------------------------------------------------------------------
                | Guardar en:
                | storage/app/public/logo_empresa/
                |--------------------------------------------------------------------------
                */

                $newLogo = $file->storeAs(
                    'logo_empresa',
                    $fileName,
                    'public'
                );


                /*
                |--------------------------------------------------------------------------
                | Actualizar logo en BD
                |--------------------------------------------------------------------------
                */

                $company->logo = $newLogo;
            }


            /*
            |--------------------------------------------------------------------------
            | Actualizar datos
            |--------------------------------------------------------------------------
            */

            $company->business_name =
                $validated['business_name'];
                  $company->razon_social =
                $validated['razon_social'];

            $company->tax_id =
                $validated['tax_id'];

            $company->website =
                $validated['website'] ?? null;


            $company->save();


            /*
            |--------------------------------------------------------------------------
            | Eliminar logo anterior
            |--------------------------------------------------------------------------
            |
            | SOLAMENTE si se subió uno nuevo.
            |--------------------------------------------------------------------------
            */

            if ($newLogo && $oldLogo) {

                if (
                    Storage::disk('public')
                    ->exists($oldLogo)
                ) {

                    Storage::disk('public')
                        ->delete($oldLogo);
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Respuesta
            |--------------------------------------------------------------------------
            */
               $access = Access::where('user_id', $user->id)
                ->where('module_id', 20) //modulo 20 empresa edit
                ->first();
            //if

            return response()->json([
                'success' => true,
                'message' => 'Empresa actualizada correctamente.',
                'data' => [
                    'id_company' => $company->id_company,
                    'business_name' => $company->business_name,
                    'tax_id' => $company->tax_id,
                    'website' => $company->website,
                    'razon_social'=>$company->razon_social,

                    'logo' => $company->logo
                        ? asset('storage/' . $company->logo)
                        : null,
                        'access'=>$access
                  
                ]
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {

            /*
            | Si la validación falla después de guardar el archivo,
            | eliminamos el archivo nuevo.
            */

            if ($newLogo) {

                Storage::disk('public')
                    ->delete($newLogo);
            }

            return response()->json([
                'success' => false,
                'message' => 'Los datos proporcionados no son válidos.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Throwable $e) {

            /*
            | Evitar archivos huérfanos.
            */

            if ($newLogo) {

                Storage::disk('public')
                    ->delete($newLogo);
            }

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la empresa.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Eliminar logo.
     */
    public function deleteLogo()
    {
        try {

            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado.'
                ], 401);
            }


            $company = Company::where(
                'id_company',
                $user->id_company
            )->first();


            if (!$company) {
                return response()->json([
                    'success' => false,
                    'message' => 'Empresa no encontrada.'
                ], 404);
            }


            if ($company->logo) {

                if (
                    Storage::disk('public')
                    ->exists($company->logo)
                ) {

                    Storage::disk('public')
                        ->delete($company->logo);
                }


                $company->logo = null;

                $company->save();
            }


            return response()->json([
                'success' => true,
                'message' => 'Logo eliminado correctamente.'
            ], 200);
        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el logo.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
