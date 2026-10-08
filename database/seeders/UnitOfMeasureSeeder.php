<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domains\Inventario\Models\Unit;

class UnitOfMeasureSeeder extends Seeder
{
    /**
     * Ejecutar el seeder.
     */
    public function run(): void
    {
        $path = database_path('data/maestros/TiposUnidad.json');

        if (!file_exists($path)) {
            $this->command->error(
                'No se encontró el archivo: ' . $path
            );

            return;
        }

        $json = file_get_contents($path);
        $units = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->command->error(
                'Error al leer el JSON: ' . json_last_error_msg()
            );

            return;
        }

        if (!is_array($units)) {
            $this->command->error(
                'El archivo TiposUnidad.json no contiene un arreglo válido.'
            );

            return;
        }

        $idCompany = 'EMP-00001';

        $count = 0;

        foreach ($units as $item) {

            if (
                !isset($item['NroUnidad']) ||
                !isset($item['UNIDAD'])
            ) {
                continue;
            }

            $code = trim((string) $item['NroUnidad']);
            $name = trim((string) $item['UNIDAD']);

            if ($code === '' || $name === '') {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Código visible
            |--------------------------------------------------------------------------
            |
            | Ejemplo:
            | 1  -> UNI-00001
            | 2  -> UNI-00002
            |
            */

            $codeUnit = 'UNI-' . str_pad(
                $code,
                5,
                '0',
                STR_PAD_LEFT
            );

            Unit::updateOrCreate(
                [
                    'code_unit' => $codeUnit,
                ],
                [
                    'unit_name'   => $name,
                    'symbol'      => $name,
                    'description' => null,
                    'status'      => true,
                ]
            );

            $count++;
        }

        $this->command->info(
            '✓ Unidades de medida registradas: ' . $count
        );
    }
}