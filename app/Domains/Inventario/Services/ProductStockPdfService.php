<?php

namespace App\Domains\Inventario\Services;



use TCPDF;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class ProductStockPdfService
{
    /*
    |--------------------------------------------------------------------------
    | GENERAR PDF
    |--------------------------------------------------------------------------
    */

    public function generate(
        $company,
        $user,
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
        | ARCHIVO
        |--------------------------------------------------------------------------
        */

        $fileName =
            'reporte_productos_' .
            now()->format('Ymd_His') .
            '.pdf';

        $relativePath =
            $directory . '/' . $fileName;

        $fullPath =
            Storage::disk('public')
                ->path($relativePath);


        /*
        |--------------------------------------------------------------------------
        | LOGO
        |--------------------------------------------------------------------------
        */

        $logoPath =
            $this->getLogoPath(
                $company
            );


        /*
        |--------------------------------------------------------------------------
        | TCPDF
        |--------------------------------------------------------------------------
        */

        $pdf = new ProductStockTcpdf(
            $company,
            $user,
            $logoPath
        );


        /*
        |--------------------------------------------------------------------------
        | CONFIGURACIÓN
        |--------------------------------------------------------------------------
        */

        $pdf->SetCreator(
            'DISAAT'
        );

        $pdf->SetAuthor(
            $user->name ?? 'Sistema'
        );

        $pdf->SetTitle(
            'Reporte de Productos y Stock'
        );

        $pdf->SetSubject(
            'Inventario por sucursal'
        );


        /*
        |--------------------------------------------------------------------------
        | MÁRGENES
        |--------------------------------------------------------------------------
        */

        $pdf->SetMargins(
            10,
            38,
            10
        );

        $pdf->SetHeaderMargin(
            5
        );

        $pdf->SetFooterMargin(
            8
        );


        /*
        |--------------------------------------------------------------------------
        | SALTO AUTOMÁTICO
        |--------------------------------------------------------------------------
        */

        $pdf->SetAutoPageBreak(
            true,
            13
        );


        /*
        |--------------------------------------------------------------------------
        | FUENTE BASE
        |--------------------------------------------------------------------------
        */

        $pdf->SetFont(
            'helvetica',
            '',
            7
        );


        /*
        |--------------------------------------------------------------------------
        | PÁGINA
        |--------------------------------------------------------------------------
        */

        $pdf->AddPage();


        /*
        |--------------------------------------------------------------------------
        | TABLA
        |--------------------------------------------------------------------------
        */

        $html =
            $this->buildTable(
                $branches,
                $products
            );


        $pdf->writeHTML(
            $html,
            true,
            false,
            true,
            false,
            ''
        );


        /*
        |--------------------------------------------------------------------------
        | GUARDAR
        |--------------------------------------------------------------------------
        */

        $pdf->Output(
            $fullPath,
            'F'
        );


        return $fullPath;
    }


    /*
    |--------------------------------------------------------------------------
    | LOGO
    |--------------------------------------------------------------------------
    */

    private function getLogoPath(
        $company
    ): ?string {

        $logoPath = null;


        /*
        |--------------------------------------------------------------------------
        | LOGO DE EMPRESA
        |--------------------------------------------------------------------------
        */

        if (!empty($company->logo)) {

            $rutaLogo =
                storage_path(
                    'app/public/' .
                    ltrim(
                        $company->logo,
                        '/'
                    )
                );


            if (
                File::exists(
                    $rutaLogo
                )
            ) {

                $logoPath =
                    $rutaLogo;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | LOGO POR DEFECTO
        |--------------------------------------------------------------------------
        */

        if (!$logoPath) {

            $logoDefault =
                public_path(
                    'logos/logo.png'
                );


            if (
                File::exists(
                    $logoDefault
                )
            ) {

                $logoPath =
                    $logoDefault;
            }
        }


        return $logoPath;
    }


    /*
    |--------------------------------------------------------------------------
    | TABLA
    |--------------------------------------------------------------------------
    */

    private function buildTable(
        $branches,
        $products
    ): string {

        /*
        |--------------------------------------------------------------------------
        | CANTIDAD DE SUCURSALES
        |--------------------------------------------------------------------------
        */

        $branchCount =
            count($branches);


        /*
        |--------------------------------------------------------------------------
        | DISTRIBUCIÓN DE COLUMNAS
        |--------------------------------------------------------------------------
        |
        | Código     = 22%
        | Producto   = 40%
        | Precio     = 13%
        | Sucursales = 25%
        |
        |--------------------------------------------------------------------------
        */

        $codeWidth = 22;
        $productWidth = 40;
        $priceWidth = 13;
        $branchesTotalWidth = 25;


        /*
        |--------------------------------------------------------------------------
        | ANCHO DE CADA SUCURSAL
        |--------------------------------------------------------------------------
        */

        if ($branchCount > 0) {

            $branchWidth =
                $branchesTotalWidth /
                $branchCount;

        } else {

            $branchWidth = 0;
        }


        /*
        |--------------------------------------------------------------------------
        | FUENTE SEGÚN CANTIDAD DE SUCURSALES
        |--------------------------------------------------------------------------
        */

        if ($branchCount >= 10) {

            $fontSize = 5.6;

        } elseif ($branchCount >= 8) {

            $fontSize = 5.9;

        } elseif ($branchCount >= 5) {

            $fontSize = 6.2;

        } else {

            $fontSize = 6.8;
        }


        /*
        |--------------------------------------------------------------------------
        | ENCABEZADO DE SUCURSALES
        |--------------------------------------------------------------------------
        */

        if ($branchCount >= 8) {

            $branchHeaderSize = 5.5;

        } elseif ($branchCount >= 5) {

            $branchHeaderSize = 5.8;

        } else {

            $branchHeaderSize = 6.2;
        }


        /*
        |--------------------------------------------------------------------------
        | HTML
        |--------------------------------------------------------------------------
        */

        $html = '

        <style>

            table {
                border-collapse: collapse;
                width: 100%;
            }

            th {
                background-color: #f0f2f4;
                color: #30343b;

                font-size: ' .
                $branchHeaderSize .
                'pt;

                font-weight: bold;

                text-align: center;

                vertical-align: middle;

                padding: 3px 2px;
            }

            td {
                color: #34383d;

                font-size: ' .
                $fontSize .
                'pt;

                vertical-align: middle;

                padding: 2.5px 2px;
            }

        </style>


        <table
            border="1"
            cellpadding="2.5"
            cellspacing="0"
            width="100%"
        >

            <thead>

                <tr>

                    <th width="' .
                        $codeWidth .
                        '%">
                        CÓDIGO
                    </th>

                    <th width="' .
                        $productWidth .
                        '%">
                        PRODUCTO
                    </th>

                    <th width="' .
                        $priceWidth .
                        '%">
                        PRECIO
                    </th>
        ';


        /*
        |--------------------------------------------------------------------------
        | SUCURSALES
        |--------------------------------------------------------------------------
        */

        foreach ($branches as $branch) {

            $html .= '

                <th width="' .
                    $branchWidth .
                    '%">

                    ' .
                    htmlspecialchars(
                        $branch->code_branch ?? '',
                        ENT_QUOTES,
                        'UTF-8'
                    ) .
                    '

                </th>

            ';
        }


        $html .= '

                </tr>

            </thead>

            <tbody>
        ';


        /*
        |--------------------------------------------------------------------------
        | PRODUCTOS
        |--------------------------------------------------------------------------
        */

        foreach ($products as $product) {

            $html .= '<tr>';


            /*
            |--------------------------------------------------------------------------
            | CÓDIGO
            |--------------------------------------------------------------------------
            */

            $code =
                $product->product_code
                ?: $product->code_product
                ?: '';


            $html .= '

                <td
                    width="' .
                    $codeWidth .
                    '%"
                    align="center"
                >

                    ' .
                    htmlspecialchars(
                        $code,
                        ENT_QUOTES,
                        'UTF-8'
                    ) .
                    '

                </td>

            ';


            /*
            |--------------------------------------------------------------------------
            | PRODUCTO
            |--------------------------------------------------------------------------
            */

            $name =
                $product->name
                ?? '';


            /*
            |--------------------------------------------------------------------------
            | MÁXIMO 20 CARACTERES
            |--------------------------------------------------------------------------
            |
            | Ejemplo:
            |
            | Compresor de Aire I...
            |
            |--------------------------------------------------------------------------
            */

            if (
                mb_strlen(
                    $name,
                    'UTF-8'
                ) > 20
            ) {

                $name =
                    mb_substr(
                        $name,
                        0,
                        20,
                        'UTF-8'
                    ) . '...';
            }


            $html .= '

                <td
                    width="' .
                    $productWidth .
                    '%"
                    align="left"
                >

                    ' .
                    htmlspecialchars(
                        $name,
                        ENT_QUOTES,
                        'UTF-8'
                    ) .
                    '

                </td>

            ';


            /*
            |--------------------------------------------------------------------------
            | PRECIO
            |--------------------------------------------------------------------------
            */

            $price =
                number_format(
                    (float)
                    (
                        $product->sale_price
                        ?? 0
                    ),
                    2,
                    '.',
                    ','
                );


            $html .= '

                <td
                    width="' .
                    $priceWidth .
                    '%"
                    align="right"
                >

                    ' .
                    $price .
                    '

                </td>

            ';


            /*
            |--------------------------------------------------------------------------
            | STOCK POR SUCURSAL
            |--------------------------------------------------------------------------
            */

            foreach (
                $branches as $branch
            ) {

                $stock = 0;


                $stockRecord =
                    $product->stocks
                        ->firstWhere(
                            'branch_id',
                            $branch->id
                        );


                if ($stockRecord) {

                    $stock =
                        $stockRecord
                            ->current_stock;
                }


                /*
                |--------------------------------------------------------------------------
                | STOCK SIN DECIMALES
                |--------------------------------------------------------------------------
                */

                $stockValue =
                    number_format(
                        (float) $stock,
                        0,
                        '.',
                        ','
                    );


                $html .= '

                    <td
                        width="' .
                        $branchWidth .
                        '%"
                        align="center"
                    >

                        ' .
                        $stockValue .
                        '

                    </td>

                ';
            }


            $html .= '</tr>';
        }


        /*
        |--------------------------------------------------------------------------
        | CERRAR TABLA
        |--------------------------------------------------------------------------
        */

        $html .= '

            </tbody>

        </table>

        ';


        return $html;
    }
}


/*
|--------------------------------------------------------------------------
| CLASE TCPDF PERSONALIZADA
|--------------------------------------------------------------------------
*/

class ProductStockTcpdf extends TCPDF
{
    protected $company;

    protected $user;

    protected $logoPath;


    /*
    |--------------------------------------------------------------------------
    | CONSTRUCTOR
    |--------------------------------------------------------------------------
    */

    public function __construct(
        $company,
        $user,
        $logoPath = null
    ) {

        $this->company =
            $company;

        $this->user =
            $user;

        $this->logoPath =
            $logoPath;


        parent::__construct(
            'P',
            'mm',
            'A4',
            true,
            'UTF-8',
            false
        );
    }


    /*
    |--------------------------------------------------------------------------
    | HEADER
    |--------------------------------------------------------------------------
    */

    public function Header()
    {

        /*
        |--------------------------------------------------------------------------
        | LOGO
        |--------------------------------------------------------------------------
        */

        if ($this->logoPath) {

            $this->Image(
                $this->logoPath,
                10,
                7,
                20,
                17,
                '',
                '',
                '',
                false,
                120,
                '',
                false,
                false,
                0,
                false,
                false,
                false
            );
        }


        /*
        |--------------------------------------------------------------------------
        | EMPRESA
        |--------------------------------------------------------------------------
        */

        $this->SetXY(
            34,
            7
        );


        $this->SetFont(
            'helvetica',
            'B',
            11
        );


        $this->SetTextColor(
            40,
            40,
            40
        );


        $this->Cell(
            0,
            5,
            $this->company
                ->business_name
                ?? 'Empresa',
            0,
            1,
            'L'
        );


        /*
        |--------------------------------------------------------------------------
        | TÍTULO
        |--------------------------------------------------------------------------
        */

        $this->SetX(
            34
        );


        $this->SetFont(
            'helvetica',
            '',
            8
        );


        $this->SetTextColor(
            90,
            90,
            90
        );


        $this->Cell(
            0,
            4,
            'Reporte de productos y stock',
            0,
            1,
            'L'
        );


        /*
        |--------------------------------------------------------------------------
        | INFORMACIÓN
        |--------------------------------------------------------------------------
        */

        $this->SetX(
            34
        );


        $this->SetFont(
            'helvetica',
            '',
            6.5
        );


        $this->SetTextColor(
            110,
            110,
            110
        );


        $this->Cell(
            0,
            4,
            'Fecha: ' .
            now()->format(
                'd/m/Y H:i'
            ) .
            '  |  Usuario: ' .
            (
                $this->user->name
                ?? 'Sistema'
            ),
            0,
            1,
            'L'
        );


        /*
        |--------------------------------------------------------------------------
        | LÍNEA
        |--------------------------------------------------------------------------
        */

        $this->SetDrawColor(
            205,
            205,
            205
        );


        $this->Line(
            10,
            27,
            200,
            27
        );


        /*
        |--------------------------------------------------------------------------
        | TÍTULO PEQUEÑO
        |--------------------------------------------------------------------------
        */

        $this->SetXY(
            10,
            30
        );


        $this->SetFont(
            'helvetica',
            'B',
            7
        );


        $this->SetTextColor(
            60,
            60,
            60
        );


        $this->Cell(
            0,
            4,
            'INVENTARIO POR SUCURSAL',
            0,
            1,
            'L'
        );


        /*
        |--------------------------------------------------------------------------
        | LÍNEA INFERIOR
        |--------------------------------------------------------------------------
        */

        $this->SetDrawColor(
            230,
            230,
            230
        );


        $this->Line(
            10,
            35,
            200,
            35
        );
    }


    /*
    |--------------------------------------------------------------------------
    | FOOTER
    |--------------------------------------------------------------------------
    */

    public function Footer()
    {

        /*
        |--------------------------------------------------------------------------
        | LÍNEA
        |--------------------------------------------------------------------------
        */

        $this->SetDrawColor(
            220,
            220,
            220
        );


        $this->Line(
            10,
            287,
            200,
            287
        );


        /*
        |--------------------------------------------------------------------------
        | TEXTO
        |--------------------------------------------------------------------------
        */

        $this->SetY(
            -10
        );


        $this->SetFont(
            'helvetica',
            '',
            6.5
        );


        $this->SetTextColor(
            120,
            120,
            120
        );


        $this->Cell(
            0,
            4,
            'Reporte de inventario  |  Página ' .
            $this->getAliasNumPage() .
            ' de ' .
            $this->getAliasNbPages(),
            0,
            0,
            'C'
        );
    }
}
