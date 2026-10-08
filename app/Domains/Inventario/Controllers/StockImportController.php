<?php

namespace App\Domains\Inventario\Controllers;



use App\Http\Controllers\Controller;
use App\Domains\Inventario\Imports\StockImport;

use Illuminate\Http\Request;

use Maatwebsite\Excel\Facades\Excel;

class StockImportController extends Controller
{
    public function importStock(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | VALIDAR
        |--------------------------------------------------------------------------
        */

        $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:xlsx,xls',
                'max:10240',
            ],

            'mode' => [
                'required',
                'in:INCREMENT,ADJUST',
            ],
        ]);


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
                'status' => 'ERROR',
                'message' =>
                    'Usuario no autenticado.',
                'errors' => [],
            ], 401);
        }


        /*
        |--------------------------------------------------------------------------
        | EMPRESA
        |--------------------------------------------------------------------------
        */

        $companyId =
            $user->id_company;


        if (!$companyId) {

            return response()->json([
                'success' => false,
                'status' => 'ERROR',
                'message' =>
                    'El usuario no tiene una empresa asignada.',
                'errors' => [],
            ], 422);
        }


        /*
        |--------------------------------------------------------------------------
        | MODO
        |--------------------------------------------------------------------------
        */

        $mode =
            strtoupper(
                trim(
                    $request->input('mode')
                )
            );


        /*
        |--------------------------------------------------------------------------
        | CREAR IMPORTADOR
        |--------------------------------------------------------------------------
        */

        $import =
            new StockImport(
                $companyId,
                $user->id,
                $mode
            );


        try {

            /*
            |--------------------------------------------------------------------------
            | IMPORTAR
            |--------------------------------------------------------------------------
            */

            Excel::import(
                $import,
                $request->file('file')
            );


            /*
            |--------------------------------------------------------------------------
            | RESULTADO
            |--------------------------------------------------------------------------
            */

            $result =
                $import->getResult();


            $registered =
                $result['registered'] ?? 0;

            $notFound =
                $result['not_found'] ?? 0;

            $invalid =
                $result['invalid'] ?? 0;

            $totalRows =
                $result['total_rows'] ?? 0;

            $status =
                $result['status'] ?? 'ERROR';


            $errors =
                $result['errors'] ?? [];


            /*
            |--------------------------------------------------------------------------
            | PRODUCTOS NO ENCONTRADOS
            |--------------------------------------------------------------------------
            */

            $productsNotFound =
                collect($errors)
                    ->filter(function ($error) {

                        return (
                            $error['type'] ?? ''
                        ) === 'PRODUCT_NOT_FOUND';

                    })
                    ->values()
                    ->toArray();


            /*
            |--------------------------------------------------------------------------
            | NOMBRE DEL MODO
            |--------------------------------------------------------------------------
            */

            $modeLabel =
                $mode === 'INCREMENT'
                    ? 'Incrementar stock'
                    : 'Ajustar stock';


            /*
            |--------------------------------------------------------------------------
            | MENSAJE
            |--------------------------------------------------------------------------
            */

            if (
                $registered > 0 &&
                $notFound === 0 &&
                $invalid === 0
            ) {

                $message =
                    "Importación completada correctamente. "
                    . "Modo: {$modeLabel}. "
                    . "Se registraron {$registered} movimientos "
                    . "de stock de {$totalRows} filas procesadas.";

            } elseif (
                $registered > 0
            ) {

                $message =
                    "Importación completada parcialmente. "
                    . "Modo: {$modeLabel}. "
                    . "Se registraron {$registered} movimientos de stock.";

                if ($notFound > 0) {

                    $message .=
                        " Hay {$notFound} productos "
                        . "que no fueron encontrados.";
                }

                if ($invalid > 0) {

                    $message .=
                        " Hay {$invalid} registros con errores.";
                }

            } else {

                $message =
                    "No se pudo registrar ningún movimiento de stock.";

                if ($notFound > 0) {

                    $message .=
                        " Hay {$notFound} productos "
                        . "que no fueron encontrados.";
                }

                if ($invalid > 0) {

                    $message .=
                        " Hay {$invalid} registros con errores.";
                }
            }


            /*
            |--------------------------------------------------------------------------
            | RESPUESTA
            |--------------------------------------------------------------------------
            */

            return response()->json([

                'success' =>
                    $result['success'] ?? false,

                'status' =>
                    $status,

                'message' =>
                    $message,

                'mode' =>
                    $mode,

                'mode_label' =>
                    $modeLabel,

                'summary' => [

                    'total_rows' =>
                        $totalRows,

                    'registered' =>
                        $registered,

                    'not_found' =>
                        $notFound,

                    'invalid' =>
                        $invalid,
                ],

                'products_not_found' =>
                    $productsNotFound,

                'errors' =>
                    $errors,

                'result' =>
                    $result,

            ], 200);

        } catch (\Throwable $e) {

            $result =
                $import->getResult();


            return response()->json([

                'success' =>
                    false,

                'status' =>
                    'ERROR',

                'message' =>
                    'Ocurrió un error al importar el stock.',

                'error' =>
                    $e->getMessage(),

                'mode' =>
                    $mode,

                'summary' => [

                    'total_rows' =>
                        $result['total_rows'] ?? 0,

                    'registered' =>
                        $result['registered'] ?? 0,

                    'not_found' =>
                        $result['not_found'] ?? 0,

                    'invalid' =>
                        $result['invalid'] ?? 0,
                ],

                'products_not_found' =>
                    collect(
                        $result['errors'] ?? []
                    )
                        ->filter(function ($error) {

                            return (
                                $error['type'] ?? ''
                            ) === 'PRODUCT_NOT_FOUND';

                        })
                        ->values()
                        ->toArray(),

                'errors' =>
                    $result['errors'] ?? [],

                'result' =>
                    $result,

            ], 500);
        }
    }
}