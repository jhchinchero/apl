<?php

namespace Database\Seeders;



use Illuminate\Database\Seeder;
use App\Domains\Inventario\Models\Product;
use App\Domains\Inventario\Models\Image;

class ProductImageSeeder extends Seeder
{
    public function run(): void
    {
        $companyId = 'EMP-00001';

        $jsonPath = database_path(
            'data/product/ControlStock.json'
        );

        if (!file_exists($jsonPath)) {
            $this->command->error(
                "No existe el archivo: {$jsonPath}"
            );

            return;
        }

        $json = file_get_contents($jsonPath);

        $items = json_decode($json, true);

        if (!is_array($items)) {
            $this->command->error(
                'No se pudo leer ControlStock.json'
            );

            return;
        }

        $registradas = 0;
        $noEncontrados = 0;
        $sinImagen = 0;

        foreach ($items as $item) {

            /*
            |--------------------------------------------------------------------------
            | NroItem
            |--------------------------------------------------------------------------
            */

            $nroItem = trim(
                (string) ($item['NroItem'] ?? '')
            );

            if ($nroItem === '') {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Buscar producto existente
            |--------------------------------------------------------------------------
            */

            $product = Product::where(
                'id_company',
                $companyId
            )
                ->where(
                    'product_code',
                    $nroItem
                )
                ->first();

            if (!$product) {

                $this->command->warn(
                    "Producto no encontrado: {$nroItem}"
                );

                $noEncontrados++;

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | IMAGEN
            |--------------------------------------------------------------------------
            |
            | Ejemplo:
            |
            | \\Servidordisaat\d\IMAGENES INVENTARIOS\INVT\o1\505.JPG
            |
            | Queremos únicamente:
            |
            | 505
            |--------------------------------------------------------------------------
            */

            $imagen = trim(
                (string) ($item['IMAGEN'] ?? '')
            );

            if ($imagen === '') {

                $this->command->warn(
                    "Sin imagen: {$nroItem}"
                );

                $sinImagen++;

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Convertir \ a /
            |--------------------------------------------------------------------------
            */

            $imagen = str_replace(
                '\\',
                '/',
                $imagen
            );

            /*
            |--------------------------------------------------------------------------
            | Obtener solamente el último archivo
            |--------------------------------------------------------------------------
            */

            $nombreArchivo = basename($imagen);

            /*
            |--------------------------------------------------------------------------
            | Quitar extensión
            |--------------------------------------------------------------------------
            |
            | 505.JPG
            | ↓
            | 505
            |--------------------------------------------------------------------------
            */

            $nombreImagen = pathinfo(
                $nombreArchivo,
                PATHINFO_FILENAME
            );

            $nombreImagen = trim($nombreImagen);

            if ($nombreImagen === '') {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Como las imágenes reales son WEBP,
            | agregamos .webp
            |--------------------------------------------------------------------------
            */

            $imagePath =
            'products/'.
                $companyId .
                '/' .
                $nombreImagen .
                '.webp';

            /*
            |--------------------------------------------------------------------------
            | Registrar / actualizar en images
            |--------------------------------------------------------------------------
            */

            $image = Image::where(
                'product_id',
                $product->id
            )->first();

            if ($image) {

                $image->update([
                    'image_path' => $imagePath,
                ]);

                $this->command->line(
                    "🔄 {$nroItem} → {$imagePath}"
                );

            } else {

                Image::create([
                    'product_id' => $product->id,
                    'image_path' => $imagePath,
                ]);

                $this->command->info(
                    "✅ {$nroItem} → {$imagePath}"
                );
            }

            $registradas++;
        }

        /*
        |--------------------------------------------------------------------------
        | RESUMEN
        |--------------------------------------------------------------------------
        */

        $this->command->newLine();

        $this->command->info(
            "Imágenes registradas: {$registradas}"
        );

        $this->command->warn(
            "Productos no encontrados: {$noEncontrados}"
        );

        $this->command->warn(
            "Sin imagen: {$sinImagen}"
        );
    }
}