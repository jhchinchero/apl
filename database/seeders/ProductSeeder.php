<?php

namespace Database\Seeders;



use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

use App\Domains\Inventario\Models\Product;
use App\Domains\Inventario\Models\StockProduct;
use App\Domains\Inventario\Models\Image;
use App\Domains\Inventario\Models\TypeData;
use App\Domains\Inventario\Models\TypeDataProduct;
use App\Domains\Inventario\Models\Brand;
use App\Domains\Inventario\Models\Category;
use App\Domains\Inventario\Models\Supplier;

class ProductSeeder extends Seeder
{
    /**
     * ============================================================
     * EMPRESA
     * ============================================================
     */
    private string $companyId = 'EMP-00001';

    private int $creados = 0;

    private int $actualizados = 0;

    private int $errores = 0;


    /**
     * ============================================================
     * RUN
     * ============================================================
     */
    public function run(): void
    {
        $jsonPath = database_path(
            'data/product/ControlStock.json'
        );

        /*
        |--------------------------------------------------------------------------
        | VERIFICAR JSON
        |--------------------------------------------------------------------------
        */

        if (!File::exists($jsonPath)) {

            $this->command->error(
                "❌ No existe el archivo:"
            );

            $this->command->error(
                $jsonPath
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | LEER JSON
        |--------------------------------------------------------------------------
        */

        $json = File::get($jsonPath);

        $productos = json_decode(
            $json,
            true
        );

        if (!is_array($productos)) {

            $this->command->error(
                '❌ ControlStock.json no contiene un JSON válido.'
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | CABECERA
        |--------------------------------------------------------------------------
        */

        $this->command->newLine();

        $this->command->info(
            '======================================================'
        );

        $this->command->info(
            '              IMPORTACIÓN DE PRODUCTOS'
        );

        $this->command->info(
            '======================================================'
        );

        $this->command->info(
            'Empresa: ' . $this->companyId
        );

        $this->command->info(
            'Productos encontrados: ' . count($productos)
        );

        $this->command->newLine();


        /*
        |--------------------------------------------------------------------------
        | RECORRER PRODUCTOS
        |--------------------------------------------------------------------------
        */

        foreach ($productos as $item) {

            try {

                DB::transaction(
                    function () use ($item) {

                        $this->importProduct(
                            $item
                        );
                    }
                );

            } catch (Throwable $e) {

                $this->errores++;

                $codigo = $item['NroItem']
                    ?? 'SIN-CODIGO';

                $this->command->error(
                    "❌ Error producto {$codigo}: {$e->getMessage()}"
                );

                Log::error(
                    'Error importando producto',
                    [
                        'producto' => $codigo,
                        'error' => $e->getMessage(),
                        'item' => $item,
                    ]
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | RESUMEN
        |--------------------------------------------------------------------------
        */

        $this->command->newLine();

        $this->command->info(
            '======================================================'
        );

        $this->command->info(
            '             IMPORTACIÓN FINALIZADA'
        );

        $this->command->info(
            '======================================================'
        );

        $this->command->info(
            "✅ Productos creados: {$this->creados}"
        );

        $this->command->info(
            "🔄 Productos actualizados: {$this->actualizados}"
        );

        $this->command->info(
            "❌ Errores: {$this->errores}"
        );

        $this->command->info(
            '======================================================'
        );
    }


    /**
     * ============================================================
     * IMPORTAR PRODUCTO
     * ============================================================
     */
    private function importProduct(array $item): void
    {
        /*
        |--------------------------------------------------------------------------
        | NRO ITEM
        |--------------------------------------------------------------------------
        */

        $oldCode = trim(
            (string)(
                $item['NroItem']
                ?? ''
            )
        );

        if ($oldCode === '') {

            throw new \Exception(
                'El producto no tiene NroItem.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | NOMBRE
        |--------------------------------------------------------------------------
        */

        $name = trim(
            (string)(
                $item['DESCRIPCION']
                ?? $item['DESCRIPCIÓN']
                ?? 'SIN DESCRIPCIÓN'
            )
        );

        if ($name === '') {

            $name = 'SIN DESCRIPCIÓN';
        }


        /*
        |--------------------------------------------------------------------------
        | DESCRIPCIÓN
        |--------------------------------------------------------------------------
        */

        $description = trim(
            (string)(
                $item['ESPECIFICACIONES']
                ?? $item['ESPECIFICACION']
                ?? ''
            )
        );


        /*
        |--------------------------------------------------------------------------
        | MODELO
        |--------------------------------------------------------------------------
        |
        | model_product no puede ser NULL.
        |
        */

        $model = trim(
            (string)(
                $item['MODELO']
                ?? $item['MODELO_PRODUCTO']
                ?? $item['MODEL']
                ?? $item['MODELO PRODUCTO']
                ?? ''
            )
        );

        if ($model === '') {

            $model = 'S/N';
        }


        /*
        |--------------------------------------------------------------------------
        | PRECIO COSTO
        |--------------------------------------------------------------------------
        */

        $purchasePrice = $this->number(
            $item['Costo']
            ?? $item['COSTO']
            ?? $item['PRECIO_COSTO']
            ?? 0
        );


        /*
        |--------------------------------------------------------------------------
        | PRECIO VENTA
        |--------------------------------------------------------------------------
        */

        $salePrice = $this->number(
            $item['PrecioVenta']
            ?? $item['PRECIOVENTA']
            ?? $item['PRECIO_VENTA']
            ?? $item['Precio Venta']
            ?? 0
        );


        /*
        |--------------------------------------------------------------------------
        | UNIDAD
        |--------------------------------------------------------------------------
        */

        $unit = trim(
            (string)(
                $item['UNIDAD']
                ?? $item['UNIDAD_MEDIDA']
                ?? $item['UNIDAD_DE_MEDIDA']
                ?? 'S/N'
            )
        );

        if ($unit === '') {

            $unit = 'S/N';
        }


        /*
        |--------------------------------------------------------------------------
        | LOTE
        |--------------------------------------------------------------------------
        */

        $lote =
            $item['LOTE']
            ?? $item['LOTE_PRODUCTO']
            ?? null;


        /*
        |--------------------------------------------------------------------------
        | MARCA
        |--------------------------------------------------------------------------
        */

        $brandId = $this->findBrandId(
            $item['MARCA']
            ?? null
        );


        /*
        |--------------------------------------------------------------------------
        | PROVEEDOR
        |--------------------------------------------------------------------------
        */

        $supplierId = $this->findSupplierId(
            $item['PROVEEDOR']
            ?? null
        );


        /*
        |--------------------------------------------------------------------------
        | CATEGORÍA
        |--------------------------------------------------------------------------
        */

        $categoryId = $this->findCategoryId(
            $item['TIPO']
            ?? null
        );


        /*
        |--------------------------------------------------------------------------
        | PRODUCT CODE
        |--------------------------------------------------------------------------
        */

        $oldCode = trim(
    (string)($item['NroItem'] ?? '')
);

if ($oldCode === '') {

    do {

        $productCode = 'PRD-' . str_pad(
            (string) random_int(1, 999999),
            6,
            '0',
            STR_PAD_LEFT
        );

    } while (
        Product::where(
            'id_company',
            $this->companyId
        )
        ->where(
            'product_code',
            $productCode
        )
        ->exists()
    );

} else {

    $productCode = $oldCode;
}


        /*
        |--------------------------------------------------------------------------
        | BUSCAR PRODUCTO
        |--------------------------------------------------------------------------
        */

        $product = Product::where(
                'id_company',
                $this->companyId
            )
            ->where(
                'product_code',
                $productCode
            )
            ->first();


        /*
        |--------------------------------------------------------------------------
        | ACTUALIZAR PRODUCTO
        |--------------------------------------------------------------------------
        */

        if ($product) {

            $product->update([

                'model_product' =>
                    $model,

                'brand_id' =>
                    $brandId,

                'supplier_id' =>
                    $supplierId,

                'category_id' =>
                    $categoryId,

                'name' =>
                    $name,

                'lote' =>
                    $lote,

                'description' =>
                    $description,

                'status' =>
                    true,

                'sale_price' =>
                    $salePrice,

                'purchase_price' =>
                    $purchasePrice,

                'unit_of_measure' =>
                    $unit,

                'product_code' =>
                    $productCode,
            ]);

            $this->actualizados++;

            $this->command->line(
                "🔄 {$product->code_product} | {$name}"
            );

        } else {

            /*
            |--------------------------------------------------------------------------
            | GENERAR PRD
            |--------------------------------------------------------------------------
            */

            $codeProduct =
                $this->generateCompanyProductId(
                    $this->companyId
                );


            /*
            |--------------------------------------------------------------------------
            | CREAR PRODUCTO
            |--------------------------------------------------------------------------
            */

            $product = Product::create([

                'code_product' =>
                    $codeProduct,

                'id_company' =>
                    $this->companyId,

                'model_product' =>
                    $model,

                'brand_id' =>
                    $brandId,

                'supplier_id' =>
                    $supplierId,

                'category_id' =>
                    $categoryId,

                'name' =>
                    $name,

                'lote' =>
                    $lote,

                'description' =>
                    $description,

                'status' =>
                    true,

                'sale_price' =>
                    $salePrice,

                'purchase_price' =>
                    $purchasePrice,

                'unit_of_measure' =>
                    $unit,

                'product_code' =>
                    $productCode,
            ]);

            $this->creados++;

            $this->command->info(
                "✅ {$codeProduct} | {$oldCode} | {$name}"
            );
        }


        /*
        |--------------------------------------------------------------------------
        | IMAGEN
        |--------------------------------------------------------------------------
        */

        $this->importImage(
            $product,
            $item
        );


        /*
        |--------------------------------------------------------------------------
        | CAPACIDAD / VOLTAJE
        |--------------------------------------------------------------------------
        */

        $this->importTypeData(
            $product,
            $item
        );


        /*
        |--------------------------------------------------------------------------
        | STOCK ALEATORIO
        |--------------------------------------------------------------------------
        */

        $this->importStock(
            $product
        );
    }


    /**
     * ============================================================
     * BUSCAR MARCA
     * ============================================================
     */
    private function findBrandId($value): ?int
    {
        $value = trim(
            (string)$value
        );

        if (
            $value === ''
            ||
            $value === '0'
        ) {

            return Brand::where(
                    'id_company',
                    $this->companyId
                )
                ->where(
                    'code_brand',
                    'BRD-00000'
                )
                ->value('id');
        }

        $code = 'BRD-' . str_pad(
            $value,
            5,
            '0',
            STR_PAD_LEFT
        );

        $brand = Brand::where(
                'id_company',
                $this->companyId
            )
            ->where(
                'code_brand',
                $code
            )
            ->first();

        if (!$brand) {

            $this->command->warn(
                "⚠ Marca {$value} no encontrada."
            );

            return null;
        }

        return $brand->id;
    }


    /**
     * ============================================================
     * BUSCAR PROVEEDOR
     * ============================================================
     */
    private function findSupplierId($value): ?int
    {
        $value = trim(
            (string)$value
        );

        if (
            $value === ''
            ||
            $value === '0'
        ) {

            return Supplier::where(
                    'id_company',
                    $this->companyId
                )
                ->where(
                    'code_supplier',
                    'SUP-00000'
                )
                ->value('id');
        }

        $code = 'SUP-' . str_pad(
            $value,
            5,
            '0',
            STR_PAD_LEFT
        );

        $supplier = Supplier::where(
                'id_company',
                $this->companyId
            )
            ->where(
                'code_supplier',
                $code
            )
            ->first();

        if (!$supplier) {

            $this->command->warn(
                "⚠ Proveedor {$value} no encontrado."
            );

            return null;
        }

        return $supplier->id;
    }


    /**
     * ============================================================
     * BUSCAR CATEGORÍA
     * ============================================================
     */
    private function findCategoryId($value): ?int
    {
        $value = trim(
            (string)$value
        );

        if (
            $value === ''
            ||
            $value === '0'
        ) {

            return Category::where(
                    'id_company',
                    $this->companyId
                )
                ->where(
                    'code_category',
                    'CAT-00000'
                )
                ->value('id');
        }

        $code = 'CAT-' . str_pad(
            $value,
            5,
            '0',
            STR_PAD_LEFT
        );

        $category = Category::where(
                'id_company',
                $this->companyId
            )
            ->where(
                'code_category',
                $code
            )
            ->first();

        if (!$category) {

            $this->command->warn(
                "⚠ Categoría {$value} no encontrada."
            );

            return null;
        }

        return $category->id;
    }


    /**
     * ============================================================
     * GENERAR CÓDIGO PRD
     * ============================================================
     */
    private function generateCompanyProductId(
        string $companyId
    ): string {

        $lastProduct = Product::where(
                'id_company',
                $companyId
            )
            ->orderByDesc('id')
            ->first();

        if (!$lastProduct) {

            $nextNumber = 1;

        } else {

            $lastCode =
                (string)$lastProduct->code_product;

            $number =
                (int)str_replace(
                    'PRD-',
                    '',
                    $lastCode
                );

            $nextNumber =
                $number + 1;
        }

        return 'PRD-' . str_pad(
            $nextNumber,
            5,
            '0',
            STR_PAD_LEFT
        );
    }


    /**
     * ============================================================
     * IMPORTAR IMAGEN
     * ============================================================
     */
    private function importImage(
        Product $product,
        array $item
    ): void {

        $imageName = trim(
            (string)(
                $item['IMAGEN']
                ?? ''
            )
        );

        if ($imageName === '') {
            return;
        }


        /*
        |--------------------------------------------------------------------------
        | NORMALIZAR NOMBRE
        |--------------------------------------------------------------------------
        */

        $imageName = basename(
            str_replace(
                '\\',
                '/',
                $imageName
            )
        );


        /*
        |--------------------------------------------------------------------------
        | POSIBLES UBICACIONES
        |--------------------------------------------------------------------------
        */

        $possiblePaths = [

            database_path(
                'data/product/images/' .
                $imageName
            ),

            database_path(
                'data/product/' .
                $imageName
            ),

            database_path(
                'data/images/' .
                $imageName
            ),

            base_path(
                'images/' .
                $imageName
            ),

            public_path(
                'images/' .
                $imageName
            ),

            public_path(
                'products/' .
                $imageName
            ),

            storage_path(
                'app/public/' .
                $imageName
            ),

            storage_path(
                'app/public/products/' .
                $imageName
            ),
        ];


        /*
        |--------------------------------------------------------------------------
        | ENCONTRAR
        |--------------------------------------------------------------------------
        */

        $sourcePath = null;

        foreach ($possiblePaths as $path) {

            if (File::exists($path)) {

                $sourcePath = $path;

                break;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | NO EXISTE
        |--------------------------------------------------------------------------
        */

        if (!$sourcePath) {

            $this->command->warn(
                "⚠ Imagen no encontrada: " .
                "{$imageName} para " .
                "{$product->code_product}"
            );

            return;
        }


        /*
        |--------------------------------------------------------------------------
        | EXTENSIÓN
        |--------------------------------------------------------------------------
        */

        $extension = strtolower(
            File::extension(
                $sourcePath
            )
        );

        if ($extension === '') {

            $extension = 'jpg';
        }


        /*
        |--------------------------------------------------------------------------
        | NOMBRE NUEVO
        |--------------------------------------------------------------------------
        */

        $fileName =
            $this->companyId .
            '_' .
            Str::uuid() .
            '.' .
            $extension;


        /*
        |--------------------------------------------------------------------------
        | CARPETA
        |--------------------------------------------------------------------------
        */

        $folder =
            'products/' .
            $this->companyId;


        /*
        |--------------------------------------------------------------------------
        | CREAR DIRECTORIO
        |--------------------------------------------------------------------------
        */

        Storage::disk(
            'public'
        )->makeDirectory(
            $folder
        );


        /*
        |--------------------------------------------------------------------------
        | GUARDAR
        |--------------------------------------------------------------------------
        */

        Storage::disk(
            'public'
        )->put(
            $folder . '/' . $fileName,
            File::get($sourcePath)
        );


        /*
        |--------------------------------------------------------------------------
        | REGISTRAR EN BD
        |--------------------------------------------------------------------------
        */

        $existingImage = Image::where(
                'product_id',
                $product->id
            )
            ->first();

        if (!$existingImage) {

            Image::create([
                'product_id' =>
                    $product->id,

                'image_path' =>
                    $folder . '/' . $fileName,
            ]);
        }

        $this->command->line(
            "   🖼 Imagen: {$imageName}"
        );
    }


    /**
     * ============================================================
     * IMPORTAR TYPE DATA
     * ============================================================
     */
    private function importTypeData(
        Product $product,
        array $item
    ): void {

        /*
        |--------------------------------------------------------------------------
        | CAPACIDAD
        |--------------------------------------------------------------------------
        */

        $capacidad = trim(
            (string)(
                $item['CAPACIDAD']
                ?? ''
            )
        );

        if ($capacidad !== '') {

            $typeData = TypeData::where(
                    'id_company',
                    $this->companyId
                )
                ->where(
                    'type_name',
                    'CAPACIDAD'
                )
                ->first();

            if ($typeData) {

                $this->saveTypeData(
                    $product,
                    $typeData,
                    $capacidad
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | VOLTAJE
        |--------------------------------------------------------------------------
        */

        $voltaje = trim(
            (string)(
                $item['VOLTAJE']
                ?? ''
            )
        );

        if ($voltaje !== '') {

            $typeData = TypeData::where(
                    'id_company',
                    $this->companyId
                )
                ->where(
                    'type_name',
                    'VOLTAJE'
                )
                ->first();

            if ($typeData) {

                $this->saveTypeData(
                    $product,
                    $typeData,
                    $voltaje
                );
            }
        }
    }


    /**
     * ============================================================
     * GUARDAR TYPE DATA
     * ============================================================
     */
    private function saveTypeData(
        Product $product,
        TypeData $typeData,
        string $value
    ): void {

        /*
        |--------------------------------------------------------------------------
        | BUSCAR
        |--------------------------------------------------------------------------
        */

        $existing = TypeDataProduct::where(
                'id_company',
                $this->companyId
            )
            ->where(
                'product_id',
                $product->id
            )
            ->where(
                'type_data_id',
                $typeData->id
            )
            ->first();


        /*
        |--------------------------------------------------------------------------
        | ACTUALIZAR
        |--------------------------------------------------------------------------
        */

        if ($existing) {

            $existing->update([
                'value' => $value,
            ]);

            return;
        }


        /*
        |--------------------------------------------------------------------------
        | CREAR
        |--------------------------------------------------------------------------
        |
        | NO usar status.
        |
        */

        TypeDataProduct::create([

            'id_company' =>
                $this->companyId,

            'product_id' =>
                $product->id,

            'type_data_id' =>
                $typeData->id,

            'value' =>
                $value,
        ]);
    }


    /**
     * ============================================================
     * IMPORTAR STOCK ALEATORIO
     * ============================================================
     */
    private function importStock(Product $product): void
{
    /*
    |--------------------------------------------------------------------------
    | SUCURSALES
    |--------------------------------------------------------------------------
    */

    $branches = [
        1,
        2
    ];

    /*
    |--------------------------------------------------------------------------
    | REGISTRAR STOCK
    |--------------------------------------------------------------------------
    */

    foreach ($branches as $branchId) {

        // TODO EL STOCK INICIA EN 0
        $cantidad = 0;

        StockProduct::updateOrCreate(
            [
                'id_company' => $this->companyId,
                'branch_id' => $branchId,
                'product_id' => $product->id,
            ],
            [
                'current_stock' => $cantidad,
                'status' => true,
                'price' => $product->purchase_price,
                'location' => null,
            ]
        );

        $this->command->line(
            "   📦 {$product->code_product} " .
            "→ BR-0000{$branchId} " .
            "→ Stock: 0"
        );
    }
}


    /**
     * ============================================================
     * CONVERTIR NÚMERO
     * ============================================================
     */
    private function number($value): float
    {
        if (
            $value === null
            ||
            $value === ''
        ) {

            return 0;
        }


        $value = trim(
            (string)$value
        );


        /*
        |--------------------------------------------------------------------------
        | QUITAR ESPACIOS
        |--------------------------------------------------------------------------
        */

        $value = str_replace(
            ' ',
            '',
            $value
        );


        /*
        |--------------------------------------------------------------------------
        | 1.234,56
        |--------------------------------------------------------------------------
        */

        if (
            str_contains($value, ',')
            &&
            str_contains($value, '.')
        ) {

            $value = str_replace(
                '.',
                '',
                $value
            );

            $value = str_replace(
                ',',
                '.',
                $value
            );

        } elseif (
            str_contains($value, ',')
        ) {

            /*
            |--------------------------------------------------------------------------
            | 1234,56
            |--------------------------------------------------------------------------
            */

            $value = str_replace(
                ',',
                '.',
                $value
            );
        }


        return (float)$value;
    }
}
