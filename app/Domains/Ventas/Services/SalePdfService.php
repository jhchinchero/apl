<?php

namespace App\Domains\Ventas\Services;

use App\Domains\Empresa\Models\Company;
use App\Domains\Ventas\Models\Sale;
use Illuminate\Support\Facades\File;
use TCPDF;

class SalePdfService
{
    /**
     * ============================================================
     * CALCULAR ALTURA DE FILA
     * ============================================================
     */
    private function calcularAlturaFila(
        TCPDF $pdf,
        array $anchos,
        array $textos,
        int $alturaLinea = 6
    ): int {

        $maxLineas = 1;

        foreach ($textos as $i => $texto) {

            $lineas = $pdf->getNumLines(
                (string) $texto,
                $anchos[$i]
            );

            $maxLineas = max(
                $maxLineas,
                $lineas
            );
        }

        return $maxLineas * $alturaLinea;
    }


    /**
     * ============================================================
     * GENERAR PDF DE VENTA
     * ============================================================
     *
     * Estructura:
     *
     * public/sales/EMP-00001/VEN-000001.pdf
     *
     * public/sales/EMP-00002/VEN-000001.pdf
     *
     */
    public function generar(
        Sale $sale,
        float $saldoInicial = 0,
        float $saldoUsado = 0,
        float $saldoRestante = 0,
        float $totalAPagar = 0
    ): array {

        /*
        |--------------------------------------------------------------------------
        | 1. CARGAR RELACIONES
        |--------------------------------------------------------------------------
        */

        $sale->load([
            'company',
            'user',
            'client',
            'branch',
            'details.product',
            'payments',
        ]);


        /*
        |--------------------------------------------------------------------------
        | 2. EMPRESA
        |--------------------------------------------------------------------------
        */

        $idCompany = $sale->id_company;

        if (!$idCompany) {

            throw new \Exception(
                'La venta no tiene una empresa asociada.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 3. OBTENER EMPRESA
        |--------------------------------------------------------------------------
        */

        $empresa = Company::where(
            'id_company',
            $idCompany
        )->first();

        if (!$empresa) {

            throw new \Exception(
                'La empresa de la venta no existe.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 4. DATOS EMPRESA
        |--------------------------------------------------------------------------
        */

        $nombreEmpresa =
            $empresa->business_name
            ?? 'Nombre Empresa';

        $nitEmpresa =
            $empresa->tax_id
            ?? '000000000';

        $website =
            $empresa->website
            ?? '';


        /*
        |--------------------------------------------------------------------------
        | 5. LOGO
        |--------------------------------------------------------------------------
        |
        | La BD guarda, por ejemplo:
        |
        | logo_empresa/logo_empresa_demo_srl_xxxx.webp
        |
        | Se busca:
        |
        | storage/app/public/logo_empresa/...
        |
        */

        $logoPath = null;

        if (!empty($empresa->logo)) {

            $rutaLogo = storage_path(
                'app/public/' .
                ltrim($empresa->logo, '/')
            );

            if (File::exists($rutaLogo)) {

                $logoPath = $rutaLogo;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | 6. LOGO POR DEFECTO
        |--------------------------------------------------------------------------
        */

        if (!$logoPath) {

            $logoDefault = public_path(
                'logos/logo.png'
            );

            if (File::exists($logoDefault)) {

                $logoPath = $logoDefault;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | 7. CREAR PDF
        |--------------------------------------------------------------------------
        */

        $pdf = new TCPDF(
            'P',
            'mm',
            'A4',
            true,
            'UTF-8',
            false
        );

        $pdf->setPrintHeader(false);

        $pdf->setPrintFooter(false);

        $pdf->SetMargins(
            15,
            15,
            15
        );

        $pdf->SetAutoPageBreak(
            true,
            15
        );

        $pdf->AddPage();


        /*
        |--------------------------------------------------------------------------
        | 8. LOGO
        |--------------------------------------------------------------------------
        */

        if (
            $logoPath &&
            File::exists($logoPath)
        ) {

            $pdf->Image(
                $logoPath,
                160,
                10,
                35,
                25
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 9. DATOS EMPRESA
        |--------------------------------------------------------------------------
        */

        $pdf->SetFont(
            'helvetica',
            'B',
            16
        );

        $pdf->Cell(
            0,
            7,
            $nombreEmpresa,
            0,
            1,
            'L'
        );


        $pdf->SetFont(
            'helvetica',
            '',
            10
        );

        $pdf->Cell(
            0,
            5,
            'NIT: ' . $nitEmpresa,
            0,
            1,
            'L'
        );


        if (!empty($website)) {

            $pdf->Cell(
                0,
                5,
                'Web: ' . $website,
                0,
                1,
                'L'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 10. LÍNEA
        |--------------------------------------------------------------------------
        */

        $pdf->SetDrawColor(
            200,
            200,
            200
        );

        $pdf->SetLineWidth(
            0.5
        );

        $pdf->Line(
            15,
            $pdf->GetY(),
            195,
            $pdf->GetY()
        );


        /*
        |--------------------------------------------------------------------------
        | 11. COMPROBANTE
        |--------------------------------------------------------------------------
        */

        $pdf->SetFont(
            'helvetica',
            'B',
            12
        );

        $pdf->Cell(
            0,
            6,
            'COMPROBANTE NRO: ' .
            $sale->receipt_number,
            0,
            1,
            'R'
        );


        $pdf->SetFont(
            'helvetica',
            '',
            10
        );

        $fecha = $sale->date
            ? \Carbon\Carbon::parse(
                $sale->date
            )->format('d/m/Y H:i')
            : now()->format('d/m/Y H:i');


        $pdf->Cell(
            0,
            6,
            'FECHA: ' . $fecha,
            0,
            1,
            'R'
        );


        /*
        |--------------------------------------------------------------------------
        | 12. CLIENTE
        |--------------------------------------------------------------------------
        */

        $pdf->Ln(3);

        $cliente = $sale->client;

        $clienteNombre =
            $cliente->name
            ?? $cliente->business_name
            ?? 'S/N';

        $clienteNIT =
            $cliente->ciNit
            ?? $cliente->nit
            ?? $cliente->ci
            ?? 'S/N';


        $pdf->SetFont(
            'helvetica',
            'B',
            10
        );

        $pdf->Cell(
            50,
            6,
            'NOMBRE O RAZÓN SOCIAL:',
            0,
            0,
            'L'
        );


        $pdf->SetFont(
            'helvetica',
            '',
            10
        );

        $pdf->Cell(
            0,
            6,
            $clienteNombre,
            0,
            1,
            'L'
        );


        /*
        |--------------------------------------------------------------------------
        | NIT / CI
        |--------------------------------------------------------------------------
        */

        $pdf->SetFont(
            'helvetica',
            'B',
            10
        );

        $pdf->Cell(
            50,
            6,
            'NIT / CI:',
            0,
            0,
            'L'
        );


        $pdf->SetFont(
            'helvetica',
            '',
            10
        );

        $pdf->Cell(
            0,
            6,
            $clienteNIT,
            0,
            1,
            'L'
        );


        /*
        |--------------------------------------------------------------------------
        | SUCURSAL
        |--------------------------------------------------------------------------
        */

        $branchName =
            $sale->branch->name
            ?? $sale->branch->branch_name
            ?? 'Sucursal';


        $pdf->SetFont(
            'helvetica',
            'B',
            10
        );

        $pdf->Cell(
            50,
            6,
            'SUCURSAL:',
            0,
            0,
            'L'
        );


        $pdf->SetFont(
            'helvetica',
            '',
            10
        );

        $pdf->Cell(
            0,
            6,
            $branchName,
            0,
            1,
            'L'
        );


        /*
        |--------------------------------------------------------------------------
        | CAJERO
        |--------------------------------------------------------------------------
        */

        $cajero =
            $sale->user->name
            ?? 'Usuario';


        $pdf->SetFont(
            'helvetica',
            'B',
            10
        );

        $pdf->Cell(
            50,
            6,
            'ATENDIDO POR:',
            0,
            0,
            'L'
        );


        $pdf->SetFont(
            'helvetica',
            '',
            10
        );

        $pdf->Cell(
            0,
            6,
            $cajero,
            0,
            1,
            'L'
        );


        $pdf->Ln(6);


        /*
        |--------------------------------------------------------------------------
        | 13. COLUMNAS
        |--------------------------------------------------------------------------
        */

        $colCode = 22;

        $colProducto = 68;

        $colCantidad = 18;

        $colPrecio = 35;

        $colSubtotal = 37;


        /*
        |--------------------------------------------------------------------------
        | 14. CABECERA
        |--------------------------------------------------------------------------
        */

        $pdf->SetFont(
            'helvetica',
            'B',
            10
        );

        $pdf->SetFillColor(
            41,
            128,
            185
        );

        $pdf->SetTextColor(
            255,
            255,
            255
        );


        $pdf->Cell(
            $colCode,
            8,
            'Código',
            1,
            0,
            'C',
            true
        );


        $pdf->Cell(
            $colProducto,
            8,
            'Producto',
            1,
            0,
            'C',
            true
        );


        $pdf->Cell(
            $colCantidad,
            8,
            'Cantidad',
            1,
            0,
            'C',
            true
        );


        $pdf->Cell(
            $colPrecio,
            8,
            'Precio',
            1,
            0,
            'C',
            true
        );


        $pdf->Cell(
            $colSubtotal,
            8,
            'Subtotal',
            1,
            1,
            'C',
            true
        );


        /*
        |--------------------------------------------------------------------------
        | 15. DETALLES
        |--------------------------------------------------------------------------
        */

        $pdf->SetFont(
            'helvetica',
            '',
            10
        );

        $pdf->SetTextColor(
            0,
            0,
            0
        );


        foreach ($sale->details as $detail) {

            $product = $detail->product;


            $codigo =
                $product->code_product
                ?? '-';


            $nombre =
                $product->name
                ?? 'Producto';


            $cantidad =
                (float) $detail->quantity;


            $precio =
                (float) $detail->unit_price;


            $subtotalDetalle =
                (float) $detail->subtotal;


            $textos = [

                $codigo,

                $nombre,

                number_format(
                    $cantidad,
                    2
                ),

                number_format(
                    $precio,
                    2
                ) . ' Bs',

                number_format(
                    $subtotalDetalle,
                    2
                ) . ' Bs',
            ];


            $anchos = [

                $colCode,

                $colProducto,

                $colCantidad,

                $colPrecio,

                $colSubtotal,
            ];


            $altura =
                $this->calcularAlturaFila(
                    $pdf,
                    $anchos,
                    $textos
                );


            $x = $pdf->GetX();

            $y = $pdf->GetY();


            /*
            |--------------------------------------------------------------------------
            | CÓDIGO
            |--------------------------------------------------------------------------
            */

            $pdf->MultiCell(
                $colCode,
                $altura,
                $textos[0],
                1,
                'C',
                false,
                0,
                $x,
                $y
            );


            $x += $colCode;


            /*
            |--------------------------------------------------------------------------
            | PRODUCTO
            |--------------------------------------------------------------------------
            */

            $pdf->MultiCell(
                $colProducto,
                $altura,
                $textos[1],
                1,
                'L',
                false,
                0,
                $x,
                $y
            );


            $x += $colProducto;


            /*
            |--------------------------------------------------------------------------
            | CANTIDAD
            |--------------------------------------------------------------------------
            */

            $pdf->MultiCell(
                $colCantidad,
                $altura,
                $textos[2],
                1,
                'C',
                false,
                0,
                $x,
                $y
            );


            $x += $colCantidad;


            /*
            |--------------------------------------------------------------------------
            | PRECIO
            |--------------------------------------------------------------------------
            */

            $pdf->MultiCell(
                $colPrecio,
                $altura,
                $textos[3],
                1,
                'R',
                false,
                0,
                $x,
                $y
            );


            $x += $colPrecio;


            /*
            |--------------------------------------------------------------------------
            | SUBTOTAL
            |--------------------------------------------------------------------------
            */

            $pdf->MultiCell(
                $colSubtotal,
                $altura,
                $textos[4],
                1,
                'R',
                false,
                1,
                $x,
                $y
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 16. TOTALES
        |--------------------------------------------------------------------------
        */

        $pdf->Ln(5);


        $subtotal =
            (float) $sale->details->sum(
                function ($detail) {

                    return (float)
                        $detail->subtotal;
                }
            );


        $descuento =
            (float) ($sale->discount ?? 0);


        $total =
            (float) $sale->total;


        /*
        |--------------------------------------------------------------------------
        | SUBTOTAL
        |--------------------------------------------------------------------------
        */

        $pdf->SetFont(
            'helvetica',
            '',
            10
        );


        $pdf->Cell(
            95,
            6,
            'Atendido por: ' . $cajero,
            0,
            0,
            'L'
        );


        $pdf->Cell(
            35,
            6,
            'Subtotal',
            0,
            0,
            'R'
        );


        $pdf->Cell(
            30,
            6,
            number_format(
                $subtotal,
                2
            ) . ' Bs',
            0,
            1,
            'R'
        );


        /*
        |--------------------------------------------------------------------------
        | DESCUENTO
        |--------------------------------------------------------------------------
        */

        if ($descuento > 0) {

            $pdf->Cell(
                95,
                6,
                '',
                0,
                0
            );

            $pdf->Cell(
                35,
                6,
                'Descuento',
                0,
                0,
                'R'
            );

            $pdf->Cell(
                30,
                6,
                '-' .
                number_format(
                    $descuento,
                    2
                ) .
                ' Bs',
                0,
                1,
                'R'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | SALDO UTILIZADO
        |--------------------------------------------------------------------------
        */

        if ($saldoUsado > 0) {

            $pdf->Cell(
                95,
                6,
                '',
                0,
                0
            );

            $pdf->Cell(
                35,
                6,
                'Saldo utilizado',
                0,
                0,
                'R'
            );

            $pdf->Cell(
                30,
                6,
                '-' .
                number_format(
                    $saldoUsado,
                    2
                ) .
                ' Bs',
                0,
                1,
                'R'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | TOTAL A PAGAR
        |--------------------------------------------------------------------------
        */

        $pdf->SetFont(
            'helvetica',
            'B',
            12
        );


        $pdf->Cell(
            95,
            8,
            '',
            0,
            0
        );


        $pdf->Cell(
            35,
            8,
            'TOTAL A PAGAR',
            0,
            0,
            'R'
        );


        $pdf->Cell(
            30,
            8,
            number_format(
                $totalAPagar > 0
                    ? $totalAPagar
                    : $total,
                2
            ) . ' Bs',
            0,
            1,
            'R'
        );


        /*
        |--------------------------------------------------------------------------
        | SALDO RESTANTE
        |--------------------------------------------------------------------------
        */

        if ($saldoRestante > 0) {

            $pdf->SetFont(
                'helvetica',
                '',
                10
            );

            $pdf->Cell(
                95,
                6,
                '',
                0,
                0
            );

            $pdf->Cell(
                35,
                6,
                'Saldo restante',
                0,
                0,
                'R'
            );

            $pdf->Cell(
                30,
                6,
                number_format(
                    $saldoRestante,
                    2
                ) . ' Bs',
                0,
                1,
                'R'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 17. MÉTODOS DE PAGO
        |--------------------------------------------------------------------------
        */

        if ($sale->payments->count() > 0) {

            $pdf->Ln(4);

            $pdf->SetFont(
                'helvetica',
                'B',
                10
            );

            $pdf->Cell(
                0,
                6,
                'MÉTODOS DE PAGO',
                0,
                1,
                'L'
            );


            $pdf->SetFont(
                'helvetica',
                '',
                9
            );


            foreach ($sale->payments as $payment) {

                $metodo =
                    $this->nombreMetodoPago(
                        $payment->payment_method
                    );


                $monto =
                    (float) $payment->amount;


                $pdf->Cell(
                    120,
                    5,
                    $metodo,
                    0,
                    0,
                    'L'
                );


                $pdf->Cell(
                    60,
                    5,
                    number_format(
                        $monto,
                        2
                    ) . ' Bs',
                    0,
                    1,
                    'R'
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | 18. PIE
        |--------------------------------------------------------------------------
        */

        $pdf->Ln(8);

        $pdf->SetFont(
            'helvetica',
            'I',
            8
        );

        $pdf->Cell(
            0,
            5,
            'Gracias por su compra.',
            0,
            1,
            'C'
        );

        $pdf->Cell(
            0,
            5,
            'Documento generado por el sistema DISAAT.',
            0,
            1,
            'C'
        );


        /*
        |--------------------------------------------------------------------------
        | 19. CREAR CARPETA DE LA EMPRESA
        |--------------------------------------------------------------------------
        |
        | Ejemplo:
        |
        | public/sales/EMP-00001/
        |
        */

        $carpetaEmpresa =
            public_path(
                'sales/' .
                $idCompany
            );


        if (!File::exists(
            $carpetaEmpresa
        )) {

            File::makeDirectory(
                $carpetaEmpresa,
                0755,
                true
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 20. NOMBRE DEL ARCHIVO
        |--------------------------------------------------------------------------
        */

        $nombreArchivo =
            $sale->code_sale .
            '.pdf';


        /*
        |--------------------------------------------------------------------------
        | 21. RUTA FÍSICA
        |--------------------------------------------------------------------------
        */

        $ruta =
            $carpetaEmpresa .
            DIRECTORY_SEPARATOR .
            $nombreArchivo;


        /*
        |--------------------------------------------------------------------------
        | 22. GENERAR PDF
        |--------------------------------------------------------------------------
        */

        $pdf->Output(
            $ruta,
            'F'
        );


        /*
        |--------------------------------------------------------------------------
        | 23. RUTA RELATIVA
        |--------------------------------------------------------------------------
        */

        $rutaRelativa =
            'sales/' .
            $idCompany .
            '/' .
            $nombreArchivo;


        /*
        |--------------------------------------------------------------------------
        | 24. URL
        |--------------------------------------------------------------------------
        */

        $url = asset(
            $rutaRelativa
        );


        /*
        |--------------------------------------------------------------------------
        | 25. ACTUALIZAR PDF_URL
        |--------------------------------------------------------------------------
        */

        $sale->pdf_url = $rutaRelativa;

        $sale->save();


        /*
        |--------------------------------------------------------------------------
        | 26. RESULTADO
        |--------------------------------------------------------------------------
        */

        return [

            'url' =>
                $url,

            'base64' =>
                base64_encode(
                    file_get_contents(
                        $ruta
                    )
                ),

            'filename' =>
                $nombreArchivo,

            'path' =>
                $rutaRelativa,
        ];
    }


    /**
     * ============================================================
     * MÉTODO DE PAGO
     * ============================================================
     */
    private function nombreMetodoPago(
        string $method
    ): string {

        return match (
            strtoupper($method)
        ) {

            'CASH' =>
                'Efectivo',

            'CARD' =>
                'Tarjeta',

            'TRANSFER' =>
                'Transferencia',

            'QR' =>
                'QR',

            'CHECK' =>
                'Cheque',

            'OTHER' =>
                'Otro',

            default =>
                $method,
        };
    }
}