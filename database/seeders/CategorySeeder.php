<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ============================================================
        // CONFIGURACIÓN
        // ============================================================

        $idCompany = 'EMP-00001';

        $path = database_path(
            'data/maestros/TiposArticulo.json'
        );

        // ============================================================
        // VERIFICAR JSON
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

        $categories = json_decode(
            $json,
            true
        );

        // ============================================================
        // VALIDAR JSON
        // ============================================================

        if (!is_array($categories)) {

            $this->command->error(
                'El archivo TiposArticulo.json no contiene un JSON válido.'
            );

            return;
        }

        // ============================================================
        // CATEGORÍA SIN ESPECIFICAR
        // ============================================================

        DB::table('categories')->updateOrInsert(
            [
                'code_category' => 'CAT-00000',
                'id_company'    => $idCompany,
            ],
            [
                'status'        => true,
                'category_name' => 'S/N',
                'updated_at'    => now(),
                'created_at'    => now(),
            ]
        );

        // ============================================================
        // PREPARAR CATEGORÍAS
        // ============================================================

        $data = [];

        foreach ($categories as $item) {

            // --------------------------------------------------------
            // VALIDAR CAMPOS DEL JSON
            // --------------------------------------------------------

            if (
                !isset($item['NroCodigo']) ||
                !isset($item['TIPO'])
            ) {
                continue;
            }

            $codigo = trim(
                (string) $item['NroCodigo']
            );

            $nombre = trim(
                (string) $item['TIPO']
            );

            // --------------------------------------------------------
            // IGNORAR REGISTROS VACÍOS
            // --------------------------------------------------------

            if ($codigo === '' || $nombre === '') {
                continue;
            }

            // --------------------------------------------------------
            // CÓDIGO DE CATEGORÍA
            // --------------------------------------------------------

            $codeCategory = 'CAT-' .
                str_pad(
                    $codigo,
                    5,
                    '0',
                    STR_PAD_LEFT
                );

            $data[] = [
                'code_category' => $codeCategory,
                'id_company'    => $idCompany,
                'status'        => true,
                'category_name' => $nombre,
                'created_at'    => now(),
                'updated_at'    => now(),
            ];
        }

        // ============================================================
        // INSERTAR / ACTUALIZAR
        // ============================================================

        $inserted = 0;

        foreach ($data as $category) {

            DB::table('categories')->updateOrInsert(
                [
                    'code_category' => $category['code_category'],
                    'id_company'    => $category['id_company'],
                ],
                [
                    'status'        => $category['status'],
                    'category_name' => $category['category_name'],
                    'updated_at'    => now(),
                    'created_at'    => $category['created_at'],
                ]
            );

            $inserted++;
        }

        // ============================================================
        // RESULTADO
        // ============================================================

        $this->command->info(
            "✓ Categorías procesadas: {$inserted}"
        );

        $this->command->info(
            "✓ Empresa: {$idCompany}"
        );
    }
}