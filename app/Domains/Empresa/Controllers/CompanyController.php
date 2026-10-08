<?php

namespace App\Domains\Empresa\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Empresa\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
     /**
     * ============================================================
     * OBTENER EMPRESA DEL USUARIO AUTENTICADO
     * ============================================================
     */
  public function getCompanyUser(Request $request)
{
    $user = $request->user();

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'Usuario no autenticado.',
        ], 401);
    }

    $company = Company::where(
        'id_company',
        $user->id_company
    )->first();

    if (!$company) {
        return response()->json([
            'success' => false,
            'message' => 'No se encontró la empresa del usuario.',
        ], 404);
    }

    return response()->json([
        'success' => true,
        'data' => $company,
    ]);
}
    /**
     * ============================================================
     * LISTAR EMPRESAS PARA TODOS 
     * ============================================================
     */
    public function index()
    {
        $companies = Company::query()
            ->orderBy('business_name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $companies,
        ]);
    }


    /**
     * ============================================================
     * MOSTRAR UNA EMPRESA
     * ============================================================
     */
    public function show(Company $company)
    {
        return response()->json([
            'success' => true,
            'data' => $company,
        ]);
    }


    /**
     * ============================================================
     * CREAR EMPRESA
     * ============================================================
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'business_name' => [
                'required',
                'string',
                'max:255',
            ],

            'tax_id' => [
                'required',
                'string',
                'max:50',
                'unique:companies,tax_id',
            ],
            'razon_social'=>[
                'required',
                'string',
                'max:255',
            ],

            'website' => [
                'nullable',
                'string',
                'max:255',
            ],

            'logo' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);


        /**
         * Generar código:
         *
         * COM-00001
         * COM-00002
         * COM-00003
         */
        $lastCompany = Company::orderByDesc('id_company')->first();

        if ($lastCompany) {

            $lastNumber = (int) str_replace(
                'COM-',
                '',
                $lastCompany->id_company
            );

            $nextNumber = $lastNumber + 1;
        } else {

            $nextNumber = 1;
        }

        $idCompany = 'COM-' . str_pad(
            $nextNumber,
            5,
            '0',
            STR_PAD_LEFT
        );


        $company = new Company();

        $company->id_company = $idCompany;
        $company->business_name = $validated['business_name'];
        $company->tax_id = $validated['tax_id'];
        $company->website = $validated['website'] ?? null;
        $company->logo = $validated['logo'] ?? null;
        $company->razon_social= $validated['razon_social'] ?? null;

        $company->save();


        return response()->json([
            'success' => true,
            'message' => 'Empresa creada correctamente.',
            'data' => $company,
        ], 201);
    }


    /**
     * ============================================================
     * ACTUALIZAR EMPRESA
     * ============================================================
     */
    public function update(Request $request, Company $company)
    {
        $validated = $request->validate([
            'business_name' => [
                'required',
                'string',
                'max:255',
            ],
             'razon_social' => [
                'required',
                'string',
                'max:255',
            ],


            'tax_id' => [
                'required',
                'string',
                'max:50',

                Rule::unique('companies', 'tax_id')
                    ->ignore($company->id_company, 'id_company'),
            ],

            'website' => [
                'nullable',
                'string',
                'max:255',
            ],

            'logo' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);


        /**
         * IMPORTANTE:
         *
         * No modificamos id_company.
         *
         * COM-00001 seguirá siendo COM-00001.
         */
        $company->business_name = $validated['business_name'];
        $company->tax_id = $validated['tax_id'];
        $company->website = $validated['website'] ?? null;
         $company->razon_social= $validated['razon_social'] ?? null;

        if (array_key_exists('logo', $validated)) {
            $company->logo = $validated['logo'];
        }

        $company->save();


        return response()->json([
            'success' => true,
            'message' => 'Empresa actualizada correctamente.',
            'data' => $company,
        ]);
    }


    /**
     * ============================================================
     * ELIMINAR EMPRESA
     * ============================================================
     */
    public function destroy(Company $company)
    {
        $company->delete();

        return response()->json([
            'success' => true,
            'message' => 'Empresa eliminada correctamente.',
        ]);
    }
}
