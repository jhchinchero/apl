<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domains\Contabilidad\Models\AccountingAccountType;

class AccountingAccountTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [

            [
                'code' => 'ACT',
                'name' => 'ACTIVO',
                'nature' => 'DEUDORA',
                'status' => true,
                'description' => 'Bienes y derechos de la empresa',
            ],

            [
                'code' => 'PAS',
                'name' => 'PASIVO',
                'nature' => 'ACREEDORA',
                'status' => true,
                'description' => 'Obligaciones y deudas de la empresa',
            ],

            [
                'code' => 'PAT',
                'name' => 'PATRIMONIO',
                'nature' => 'ACREEDORA',
                'status' => true,
                'description' => 'Patrimonio y capital de la empresa',
            ],

            [
                'code' => 'ING',
                'name' => 'INGRESO',
                'nature' => 'ACREEDORA',
                'status' => true,
                'description' => 'Ingresos generados por la empresa',
            ],

            [
                'code' => 'COS',
                'name' => 'COSTO',
                'nature' => 'DEUDORA',
                'status' => true,
                'description' => 'Costos asociados a las operaciones',
            ],

            [
                'code' => 'GAS',
                'name' => 'GASTO',
                'nature' => 'DEUDORA',
                'status' => true,
                'description' => 'Gastos de la empresa',
            ],

        ];

        foreach ($types as $type) {

            AccountingAccountType::updateOrCreate(
                [
                    'code' => $type['code'],
                ],
                $type
            );
        }
    }
}