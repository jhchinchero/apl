<?php

namespace App\Domains\Inventario\Services;

use App\Domains\Inventario\Services\ProductStockExport;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ProductStockExcelService
{
    public function generate(
        $company,
        $branches,
        $products
    ): string {

        /*
        |--------------------------------------------------------------------------
        | CARPETA
        |--------------------------------------------------------------------------
        */

        $directory =
            'reports/products/' .
            $company->id_company;

        Storage::disk('public')
            ->makeDirectory($directory);

        /*
        |--------------------------------------------------------------------------
        | NOMBRE
        |--------------------------------------------------------------------------
        */

        $fileName =
            'reporte_productos_' .
            now()->format('Ymd_His') .
            '.xlsx';

        $relativePath =
            $directory . '/' . $fileName;

        /*
        |--------------------------------------------------------------------------
        | GENERAR EXCEL
        |--------------------------------------------------------------------------
        */

        Excel::store(
            new ProductStockExport(
                $branches,
                $products
            ),
            $relativePath,
            'public'
        );

        return Storage::disk('public')
            ->path($relativePath);
    }
}