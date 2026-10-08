<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domains\Inventario\Models\TypeData;

class TypeDataSeeder extends Seeder
{
    /**
     * Ejecutar el seeder.
     */
    public function run(): void
    {
        $idCompany = 'EMP-00001';

        $types = [
            [
                'id_type_data' => 'TYPE-DATA-00001',
                'type_name'    => 'CAPACIDAD',
                'code'         => 'CAP',
            ],
            [
                'id_type_data' => 'TYPE-DATA-00002',
                'type_name'    => 'VOLTAJE',
                'code'         => 'VOL',
            ],
        ];

        foreach ($types as $type) {

            TypeData::updateOrCreate(
                [
                    'id_type_data' => $type['id_type_data'],
                    'id_company'   => $idCompany,
                ],
                [
                    'status'    => true,
                    'type_name' => $type['type_name'],
                    'code'      => $type['code'],
                ]
            );
        }

        $this->command->info(
            '✓ Tipos de datos registrados: ' . count($types)
        );
    }
}