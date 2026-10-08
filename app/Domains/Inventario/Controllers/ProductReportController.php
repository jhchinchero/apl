<?php

namespace App\Domains\Inventario\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Empresa\Models\Company;
use App\Domains\Inventario\Services\ProductReportService;
use App\Domains\Inventario\Services\ProductStockPdfService;
use App\Domains\Inventario\Services\ProductStockExcelService;
use Illuminate\Http\Request;

class ProductReportController extends Controller
{
    protected $reportService;

    protected $pdfService;

    protected $excelService;

    public function __construct(
        ProductReportService $reportService,
        ProductStockPdfService $pdfService,
        ProductStockExcelService $excelService
    ) {

        $this->reportService =
            $reportService;

        $this->pdfService =
            $pdfService;

        $this->excelService =
            $excelService;
    }

    /*
    |--------------------------------------------------------------------------
    | LISTAR
    |--------------------------------------------------------------------------
    */

    public function index(
        Request $request
    ) {

        /*
        |--------------------------------------------------------------------------
        | USUARIO AUTENTICADO
        |--------------------------------------------------------------------------
        */

        $user =
            $request->user();

        if (!$user) {

            return response()->json([
                'success' => false,
                'message' =>
                    'Usuario no autenticado.'
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | EMPRESA DEL USUARIO
        |--------------------------------------------------------------------------
        */

        $idCompany =
            $user->id_company;

        /*
        |--------------------------------------------------------------------------
        | DATOS
        |--------------------------------------------------------------------------
        */

        $data =
            $this->reportService
                ->getProductsWithStock(
                    $idCompany
                );

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | PDF
    |--------------------------------------------------------------------------
    */

    public function pdf(
        Request $request
    ) {

        /*
        |--------------------------------------------------------------------------
        | USUARIO
        |--------------------------------------------------------------------------
        */

        $user =
            $request->user();

        if (!$user) {

            return response()->json([
                'success' => false,
                'message' =>
                    'Usuario no autenticado.'
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | EMPRESA
        |--------------------------------------------------------------------------
        */

        $idCompany =
            $user->id_company;

        $company =
            Company::where(
                'id_company',
                $idCompany
            )->first();

        if (!$company) {

            return response()->json([
                'success' => false,
                'message' =>
                    'No se encontró la empresa.'
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | DATOS
        |--------------------------------------------------------------------------
        */

        $data =
            $this->reportService
                ->getProductsWithStock(
                    $idCompany
                );

        /*
        |--------------------------------------------------------------------------
        | GENERAR PDF
        |--------------------------------------------------------------------------
        */

        $filePath =
            $this->pdfService->generate(
                $company,
                $user,
                $data['branches'],
                $data['products']
            );

        /*
        |--------------------------------------------------------------------------
        | DESCARGAR
        |--------------------------------------------------------------------------
        */

        return response()->download(
            $filePath,
            basename($filePath),
            [
                'Content-Type' =>
                    'application/pdf'
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | EXCEL
    |--------------------------------------------------------------------------
    */

    public function excel(
        Request $request
    ) {

        /*
        |--------------------------------------------------------------------------
        | USUARIO
        |--------------------------------------------------------------------------
        */

        $user =
            $request->user();

        if (!$user) {

            return response()->json([
                'success' => false,
                'message' =>
                    'Usuario no autenticado.'
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | EMPRESA
        |--------------------------------------------------------------------------
        */

        $idCompany =
            $user->id_company;

        $company =
            Company::where(
                'id_company',
                $idCompany
            )->first();

        if (!$company) {

            return response()->json([
                'success' => false,
                'message' =>
                    'No se encontró la empresa.'
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | DATOS
        |--------------------------------------------------------------------------
        */

        $data =
            $this->reportService
                ->getProductsWithStock(
                    $idCompany
                );

        /*
        |--------------------------------------------------------------------------
        | GENERAR EXCEL
        |--------------------------------------------------------------------------
        */

        $filePath =
            $this->excelService->generate(
                $company,
                $data['branches'],
                $data['products']
            );

        /*
        |--------------------------------------------------------------------------
        | DESCARGAR
        |--------------------------------------------------------------------------
        */

        return response()->download(
            $filePath,
            basename($filePath),
            [
                'Content-Type' =>
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ]
        );
    }
}