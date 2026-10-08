<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domains\Empresa\Models\Company;
use App\Domains\Empresa\Models\Branch;
use App\Domains\Empresa\Models\PointOfSale;

class BranchSeeder extends Seeder
{
      public function run(): void
    {
        // ============================================================
        // EMPRESA
        // ============================================================

        $company = Company::first();

        if (!$company) {

            $this->command->warn(
                'No companies found. Run CompanySeeder first.'
            );

            return;
        }


        // ============================================================
        // SUCURSALES
        // ============================================================

        $branches = [

            [
                'code_branch'  => 'BR-00001',
                'id_company'   => $company->id_company,
                'status'       => true,
                'branch_name'  => 'PRINCIPAL',
                'department'   => 'La Paz',
                'municipality' => 'La Paz',
                'address'      => 'Sucursal Principal',
                'contact_name' => 'Sucursal Principal',
                'phone'        => '77777777',
                'codigo'       => 0,
            ],

            [
                'code_branch'  => 'BR-00002',
                'id_company'   => $company->id_company,
                'status'       => true,
                'branch_name'  => 'EL ALTO',
                'department'   => 'La Paz',
                'municipality' => 'El Alto',
                'address'      => 'Sucursal El Alto',
                'contact_name' => 'Sucursal El Alto',
                'phone'        => '78888888',
                'codigo'       => 1,
            ]

        ];


        // ============================================================
        // CREAR SUCURSALES
        // ============================================================

        foreach ($branches as $branchData) {

            $branch = Branch::updateOrCreate(

                [
                    'code_branch' =>
                        $branchData['code_branch'],

                    'id_company' =>
                        $company->id_company,
                ],

                $branchData
            );


            // ========================================================
            // OBTENER ÚLTIMO POS DE ESTA SUCURSAL
            // ========================================================

            $lastCodigo = PointOfSale::where(
                'id_company',
                $company->id_company
            )
            ->where(
                'branch_id',
                $branch->id
            )
            ->max('codigo');


            // ========================================================
            // GENERAR CODIGO
            // ========================================================
            //
            // Primera caja de cada sucursal = 0
            //

            if ($lastCodigo === null) {

                $codigo = 0;

            } else {

                $codigo = (int) $lastCodigo + 1;
            }


            // ========================================================
            // CREAR POS PRINCIPAL
            // ========================================================

            $pos = PointOfSale::updateOrCreate(

                [
                    'id_company' =>
                        $company->id_company,

                    'branch_id' =>
                        $branch->id,

                    'codigo' =>
                        0,
                ],

                [
                    'name' =>
                        'Caja Principal',

                    'status' =>
                        true,
                ]
            );


            // ========================================================
            // INFORMACIÓN
            // ========================================================

            $this->command->info(
                "Sucursal {$branch->code_branch} " .
                "→ {$branch->branch_name} " .
                "→ POS codigo {$pos->codigo}"
            );
        }
    }

   
}