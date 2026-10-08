<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use App\Domains\Inventario\Models\Supplier;

class SupplierSeeder extends Seeder
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
            'data/maestros/Proveedores.json'
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

        $suppliers = json_decode(
            $json,
            true
        );

        // ============================================================
        // VALIDAR JSON
        // ============================================================

        if (!is_array($suppliers)) {

            $this->command->error(
                'El archivo Proveedores.json no contiene un JSON válido.'
            );

            return;
        }

        // ============================================================
        // PROVEEDOR POR DEFECTO
        // ============================================================

        Supplier::updateOrCreate(
            [
                'code_supplier' => 'SUP-00000',
                'id_company'    => $idCompany,
            ],
            [
                'status'    => true,
                'name'      => 'SIN PROVEEDOR',
                'email'     => 'sinproveedor@local.local',
                'phone'     => '',
                'address'   => '',
                'reference' => '',
            ]
        );

        // ============================================================
        // REGISTRAR PROVEEDORES
        // ============================================================

        $count = 0;

        foreach ($suppliers as $item) {

            // --------------------------------------------------------
            // VALIDAR CÓDIGO Y NOMBRE
            // --------------------------------------------------------

            if (
                !isset($item['CODIGO']) ||
                !isset($item['PROVEEDOR'])
            ) {
                continue;
            }

            $codigo = trim(
                (string) $item['CODIGO']
            );

            $nombre = trim(
                (string) $item['PROVEEDOR']
            );

            // --------------------------------------------------------
            // IGNORAR REGISTROS VACÍOS
            // --------------------------------------------------------

            if ($codigo === '' || $nombre === '') {
                continue;
            }

            // --------------------------------------------------------
            // CELULAR
            // --------------------------------------------------------

            $phone = '';

            if (
                isset($item['CELULAR']) &&
                trim((string) $item['CELULAR']) !== ''
            ) {
                $phone = trim(
                    (string) $item['CELULAR']
                );
            }

            // --------------------------------------------------------
            // SI NO TIENE CELULAR, USAR TELÉFONO
            // --------------------------------------------------------

            if (
                $phone === '' &&
                isset($item['TELEFONO'])
            ) {
                $phone = trim(
                    (string) $item['TELEFONO']
                );
            }

            // --------------------------------------------------------
            // EMAIL
            // --------------------------------------------------------

            $email = '';

            if (isset($item['EMAIL'])) {
                $email = trim(
                    (string) $item['EMAIL']
                );
            }

            // --------------------------------------------------------
            // DIRECCIÓN
            // --------------------------------------------------------

            $address = '';

            if (isset($item['DIRECCION'])) {
                $address = trim(
                    (string) $item['DIRECCION']
                );
            }

            // --------------------------------------------------------
            // CÓDIGO DEL PROVEEDOR
            // --------------------------------------------------------

            $codeSupplier = 'SUP-' .
                str_pad(
                    $codigo,
                    5,
                    '0',
                    STR_PAD_LEFT
                );

            // --------------------------------------------------------
            // CREAR / ACTUALIZAR
            // --------------------------------------------------------

            Supplier::updateOrCreate(
                [
                    'code_supplier' => $codeSupplier,
                    'id_company'    => $idCompany,
                ],
                [
                    'status'    => true,
                    'name'      => $nombre,
                    'email'     => $email,
                    'phone'     => $phone,
                    'address'   => $address,
                    'reference' => '',
                ]
            );

            $count++;
        }

        // ============================================================
        // RESULTADO
        // ============================================================

        $this->command->info(
            "✓ Proveedores procesados: {$count}"
        );

        $this->command->info(
            "✓ Empresa: {$idCompany}"
        );
    }
}