<?php

namespace App\Domains\Cotizacion\Services;

use App\Domains\Empresa\Models\Company;
use Illuminate\Support\Facades\File;
use TCPDF;

class CotizacionService
{
    /**
     * Calcula la altura necesaria de una fila según el texto.
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
     * Generar PDF de Cotización / Proforma.
     *
     * Cada empresa tendrá su propia carpeta:
     *
     * public/quotation/EMP-00001/
     * public/quotation/EMP-00002/
     */
    public function generar(
        array $productos,
        string $cliente,
        string $cajero,
        float $descuentoGlobal,
        string $receiptNumber,
        ?string $clienteNIT = 'S/N',
        ?string $idCompany = null
    ): array {

        /* ============================================================
         * 1. OBTENER EMPRESA
         * ============================================================
         */

        if (!$idCompany) {
            throw new \Exception(
                'No se recibió el ID de la empresa.'
            );
        }

        $empresa = Company::where(
            'id_company',
            $idCompany
        )->first();

        if (!$empresa) {
            throw new \Exception(
                'La empresa no existe.'
            );
        }


        /* ============================================================
         * 2. DATOS DE EMPRESA
         * ============================================================
         */

        $nombreEmpresa =
            $empresa->business_name
            ?? 'Nombre Empresa';

        $ruc =
            $empresa->tax_id
            ?? '000000000';

        $website =
            $empresa->website
            ?? '';


        /* ============================================================
         * 3. OBTENER LOGO
         * ============================================================
         *
         * Ejemplo guardado en BD:
         *
         * logo_empresa/logo_empresa_demo_srl_dkiCvP4lC1hj.webp
         *
         * Buscará:
         *
         * public/logo_empresa/
         * logo_empresa_demo_srl_dkiCvP4lC1hj.webp
         */

        $logoPath = null;


        if (!empty($empresa->logo)) {


            $rutaLogo = storage_path(
                'app/public/' . ltrim($empresa->logo, '/')
            );

            if (File::exists($rutaLogo)) {
                $logoPath = $rutaLogo;
            }
        }



        /* ============================================================
         * 4. LOGO POR DEFECTO
         * ============================================================
         */

        if (!$logoPath) {

            $logoDefault = public_path(
                'logos/logo.png'
            );

            if (File::exists($logoDefault)) {

                $logoPath = $logoDefault;
            }
        }


        /* ============================================================
         * 5. CREAR PDF
         * ============================================================
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


        /* ============================================================
         * 6. LOGO
         * ============================================================
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


        /* ============================================================
         * 7. DATOS EMPRESA
         * ============================================================
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
            "NIT: $ruc",
            0,
            1,
            'L'
        );


        if (!empty($website)) {

            $pdf->Cell(
                0,
                5,
                "Web: $website",
                0,
                1,
                'L'
            );
        }


        /* ============================================================
         * 8. LÍNEA
         * ============================================================
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


        /* ============================================================
         * 9. DATOS PROFORMA
         * ============================================================
         */

        $pdf->SetFont(
            'helvetica',
            'B',
            12
        );

        $pdf->Cell(
            0,
            6,
            "PROFORMA NRO: $receiptNumber",
            0,
            1,
            'R'
        );


        $pdf->SetFont(
            'helvetica',
            '',
            10
        );

        $pdf->Cell(
            0,
            6,
            'FECHA: ' .
                now()->format('d/m/Y'),
            0,
            1,
            'R'
        );


        $pdf->Ln(3);


        /* ============================================================
         * 10. CLIENTE
         * ============================================================
         */

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
            $cliente ?: 'S/N',
            0,
            1,
            'L'
        );


        /* ============================================================
         * NIT / CI CLIENTE
         * ============================================================
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
            $clienteNIT ?: 'S/N',
            0,
            1,
            'L'
        );


        $pdf->Ln(6);


        /* ============================================================
         * 11. COLUMNAS
         * ============================================================
         */

        $colCode = 20;
        $colProducto = 45;
        $colAclaracion = 40;
        $colCantidad = 18;
        $colPrecio = 30;
        $colSubtotal = 32;


        /* ============================================================
         * 12. CABECERA TABLA
         * ============================================================
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
            $colAclaracion,
            8,
            'Aclaración',
            1,
            0,
            'C',
            true
        );

        $pdf->Cell(
            $colCantidad,
            8,
            'Cant.',
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


        /* ============================================================
         * 13. PRODUCTOS
         * ============================================================
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

        $subtotal = 0;


        foreach ($productos as $p) {

            $precio =
                (float) (
                    $p['precio'] ?? 0
                );

            $cantidad =
                (float) (
                    $p['cant'] ?? 0
                );

            $lineTotal =
                $precio * $cantidad;

            $subtotal += $lineTotal;


            $anchos = [

                $colCode,

                $colProducto,

                $colAclaracion,

                $colCantidad,

                $colPrecio,

                $colSubtotal
            ];


            $textos = [

                $p['code'] ?? '',

                $p['desc'] ?? '',

                $p['aclaracion'] ?? '',

                $p['cant'] ?? '',

                number_format(
                    $precio,
                    2
                ),

                number_format(
                    $lineTotal,
                    2
                )
            ];


            $altura =
                $this->calcularAlturaFila(
                    $pdf,
                    $anchos,
                    $textos
                );


            $x =
                $pdf->GetX();

            $y =
                $pdf->GetY();


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


            $pdf->MultiCell(
                $colAclaracion,
                $altura,
                $textos[2],
                1,
                'L',
                false,
                0,
                $x,
                $y
            );


            $x += $colAclaracion;


            $pdf->MultiCell(
                $colCantidad,
                $altura,
                $textos[3],
                1,
                'C',
                false,
                0,
                $x,
                $y
            );


            $x += $colCantidad;


            $pdf->MultiCell(
                $colPrecio,
                $altura,
                $textos[4],
                1,
                'R',
                false,
                0,
                $x,
                $y
            );


            $x += $colPrecio;


            $pdf->MultiCell(
                $colSubtotal,
                $altura,
                $textos[5],
                1,
                'R',
                false,
                1,
                $x,
                $y
            );
        }


        /* ============================================================
         * 14. DESCUENTO
         * ============================================================
         */

        $descuentoMonto =
            $subtotal *
            ($descuentoGlobal / 100);


        $total =
            $subtotal -
            $descuentoMonto;


        /* ============================================================
         * 15. TOTALES
         * ============================================================
         */

        $pdf->Ln(4);

        $pdf->SetFont(
            'helvetica',
            '',
            10
        );


        /* Atendido + subtotal */

        $pdf->Cell(
            95,
            6,
            "Atendido por: $cajero",
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


        /* Descuento */

        if ($descuentoGlobal > 0) {

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
                "Descuento ({$descuentoGlobal}%)",
                0,
                0,
                'R'
            );

            $pdf->Cell(
                30,
                6,
                '-' .
                    number_format(
                        $descuentoMonto,
                        2
                    ) .
                    ' Bs',
                0,
                1,
                'R'
            );
        }


        /* Total */

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
                $total,
                2
            ) . ' Bs',
            0,
            1,
            'R'
        );


        /* ============================================================
         * 16. PIE
         * ============================================================
         */

        $pdf->Ln(8);

        $pdf->SetFont(
            'helvetica',
            'I',
            8
        );

        $pdf->Cell(
            0,
            6,
            'Duración de la oferta: 20 días. Gracias por su preferencia. Incluye impuestos por ley.',
            0,
            1,
            'C'
        );


        /* ============================================================
         * 17. CARPETA DE LA EMPRESA
         * ============================================================
         *
         * Ejemplo:
         *
         * public/quotation/EMP-00001/
         *
         */

        $carpetaEmpresa = public_path(
            'quotation/' . $idCompany
        );


        if (!File::exists($carpetaEmpresa)) {

            File::makeDirectory(
                $carpetaEmpresa,
                0755,
                true
            );
        }


        /* ============================================================
         * 18. NOMBRE DEL ARCHIVO
         * ============================================================
         *
         * Ejemplo:
         *
         * COT-000001.pdf
         *
         */

        $nombreArchivo =
            $receiptNumber . '.pdf';


        /* ============================================================
         * 19. RUTA FÍSICA
         * ============================================================
         *
         * public/quotation/EMP-00001/COT-000001.pdf
         */

        $ruta =
            $carpetaEmpresa .
            DIRECTORY_SEPARATOR .
            $nombreArchivo;


        /* ============================================================
         * 20. GENERAR PDF
         * ============================================================
         */

        $pdf->Output(
            $ruta,
            'F'
        );


        /* ============================================================
         * 21. RUTA RELATIVA
         * ============================================================
         *
         * Esto es lo que recomiendo guardar
         * en quotation.pdf_name
         *
         * quotation/EMP-00001/COT-000001.pdf
         */

        $rutaRelativa =
            'quotation/' .
            $idCompany .
            '/' .
            $nombreArchivo;


        /* ============================================================
         * 22. URL PÚBLICA
         * ============================================================
         */

        $url = asset(
            $rutaRelativa
        );


        /* ============================================================
         * 23. RESPUESTA
         * ============================================================
         */

        return [

            'url' => $url,

            'base64' => base64_encode(
                file_get_contents($ruta)
            ),

            'filename' =>
            $nombreArchivo,

            'path' =>
            $rutaRelativa,

            'company' => [

                'id_company' =>
                $empresa->id_company,

                'business_name' =>
                $empresa->business_name,

                'tax_id' =>
                $empresa->tax_id,

                'logo' =>
                $empresa->logo,
            ],
        ];
    }
}
