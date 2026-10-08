<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use App\Domains\Inventario\Models\Brand;

class BrandSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ============================================================
        // EMPRESA
        // ============================================================

        $idCompany = 'EMP-00001';

        // ============================================================
        // ARCHIVO JSON
        // ============================================================

        $path = database_path(
            'data/maestros/Marcas.json'
        );

        // ============================================================
        // VERIFICAR ARCHIVO
        // ============================================================

        if (!File::exists($path)) {

            $this->command->error(
                "No se encontró el archivo: {$path}"
            );

            return;
        }

        // ============================================================
        // LEER JSON
        // ============================================================

        $json = File::get($path);

        $brands = json_decode(
            $json,
            true
        );

        // ============================================================
        // VALIDAR JSON
        // ============================================================

        if (!is_array($brands)) {

            $this->command->error(
                'El archivo Marcas.json no contiene un JSON válido.'
            );

            return;
        }

        // ============================================================
        // MARCA SIN ESPECIFICAR
        // ============================================================

        Brand::updateOrCreate(
            [
                'code_brand' => 'BRD-00000',
                'id_company' => $idCompany,
            ],
            [
                'status'     => true,
                'brand_name' => 'S/N',
            ]
        );

        // ============================================================
        // REGISTRAR MARCAS
        // ============================================================

        $count = 0;

        foreach ($brands as $item) {

            // --------------------------------------------------------
            // VALIDAR CAMPOS
            // --------------------------------------------------------

            if (
                !isset($item['NroCodigo']) ||
                !isset($item['MARCA'])
            ) {
                continue;
            }

            $codigo = trim(
                (string) $item['NroCodigo']
            );

            $nombre = trim(
                (string) $item['MARCA']
            );

            // --------------------------------------------------------
            // IGNORAR VACÍOS
            // --------------------------------------------------------

            if ($codigo === '' || $nombre === '') {
                continue;
            }

            // --------------------------------------------------------
            // GENERAR CÓDIGO
            // --------------------------------------------------------

            $codeBrand = 'BRD-' .
                str_pad(
                    $codigo,
                    5,
                    '0',
                    STR_PAD_LEFT
                );

            // --------------------------------------------------------
            // CREAR / ACTUALIZAR
            // --------------------------------------------------------

            Brand::updateOrCreate(
                [
                    'code_brand' => $codeBrand,
                    'id_company' => $idCompany,
                ],
                [
                    'status'     => true,
                    'brand_name' => $nombre,
                ]
            );

            $count++;
        }

        // ============================================================
        // MENSAJE
        // ============================================================

        $this->command->info(
            "✓ Marcas procesadas: {$count}"
        );

        $this->command->info(
            "✓ Empresa: {$idCompany}"
        );
    }
}