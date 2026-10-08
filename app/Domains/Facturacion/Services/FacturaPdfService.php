<?php

namespace App\Domains\Facturacion\Services;


use App\Domains\Empresa\Models\Company;
use App\Domains\Facturacion\Models\FacturaCabecera;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use TCPDF;

class FacturaPdfService
{
    private const X = 10;      // margen izquierdo
    private const ANCHO = 190; // ancho útil A4

    // Columnas: código | cantidad | unidad | descripción | precio | descuento | subtotal (suma = 190)
    private const COLS = [28, 18, 24, 52, 26, 20, 22];

    /*
    |--------------------------------------------------------------------------
    | PALETA (RGB)
    |--------------------------------------------------------------------------
    */
    private const C_PRIMARIO  = [30, 64, 175];   // azul profundo
    private const C_PRIM_SOFT = [219, 234, 254]; // azul muy claro
    private const C_TEXTO     = [15, 23, 42];    // slate 900
    private const C_MUTED     = [100, 116, 139]; // slate 500
    private const C_BORDE     = [203, 213, 225]; // slate 300
    private const C_FONDO     = [241, 245, 249]; // slate 100
    private const C_ZEBRA     = [248, 250, 252]; // slate 50

    /**
     * Genera el PDF de la factura y lo guarda en:
     * public/facturas/{id_company}/factura-{numero}-{cuf8}.pdf
     *
     * @param bool|null $sinValorLegal null = automático (marca de agua fuera de producción)
     */
    public function generar(FacturaCabecera $factura, ?bool $sinValorLegal = null): array
    {
        $factura->loadMissing('detalles');

        $sinValorLegal ??= !app()->environment('production');//true o false
        $anulada       ??= (int) $factura->estado === 2; // new ajusta al valor de "anulada" en tu BD


        $logo = $this->resolverLogo($factura);

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(self::X, 10, self::X);
        $pdf->SetAutoPageBreak(true, 10);
        $pdf->AddPage();

        $pdf->SetDrawColor(...self::C_BORDE);
        $pdf->SetLineWidth(0.2);

        /* ---------------- CABECERA ---------------- */

        $yFin = $this->dibujarEncabezado($pdf, $factura, $logo);

        // línea de acento
        $pdf->SetDrawColor(...self::C_PRIMARIO);
        $pdf->SetLineWidth(0.7);
        $pdf->Line(self::X, $yFin + 2, self::X + self::ANCHO, $yFin + 2);
        $pdf->SetDrawColor(...self::C_BORDE);
        $pdf->SetLineWidth(0.2);

        if ($sinValorLegal) {//quitar la para el valor legal
            $this->marcaDeAgua($pdf, $yFin - 6);
        }

        /* ---------------- TÍTULO ---------------- */

        $pdf->SetTextColor(...self::C_PRIMARIO);
        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->SetXY(self::X, $yFin + 8);
        $pdf->Cell(self::ANCHO, 8, 'FACTURA', 0, 1, 'C');

        $pdf->SetTextColor(...self::C_MUTED);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetX(self::X);
        $pdf->Cell(self::ANCHO, 5, '(Con Derecho a Crédito Fiscal)', 0, 1, 'C');

        /* ---------------- DATOS CLIENTE ---------------- */

        $y = $pdf->GetY() + 6;

        $fecha = Carbon::parse($factura->fecha_emision)->format('d/m/Y h:i A');

        $documento = trim(
            $factura->numero_documento .
                ($factura->complemento ? '-' . $factura->complemento : '')
        );

        $pdf->SetFillColor(...self::C_FONDO);
        $pdf->SetDrawColor(...self::C_BORDE);
        $pdf->RoundedRect(self::X, $y, self::ANCHO, 15, 2, '1111', 'DF');

        $this->filaDato($pdf, $y + 2.5, 'Fecha:', $fecha, 'NIT/CI/CEX:', $documento);
        $this->filaDato($pdf, $y + 8, 'Nombre/Razón Social:', $factura->nombre_razon_social, 'Cod. Cliente:', $factura->codigo_cliente);

        /* ---------------- TABLA DETALLE ---------------- */

        $pdf->SetY($y + 20);
        $this->cabeceraTabla($pdf);

        $aligns = ['L', 'R', 'L', 'L', 'R', 'R', 'R'];

        foreach ($factura->detalles as $idx => $d) {

            $textos = [
                $d->codigo_producto,
                $this->n($d->cantidad),
                //$d->unidad_medida,
                   $this->obtenerDescripcionUnidad(
        (int) $d->unidad_medida,
        $factura->id_company
    ),
                $d->descripcion,
                $this->n($d->precio_unitario),
                $this->n($d->monto_descuento ?? 0),
                $this->n($d->sub_total),
            ];

            $pdf->SetFont('helvetica', '', 8);

            $altura = $this->alturaFila($pdf, $textos);

            if ($pdf->GetY() + $altura > 270) {
                $pdf->AddPage();
                $this->cabeceraTabla($pdf);
            }

            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetTextColor(...self::C_TEXTO);
            $pdf->SetDrawColor(...self::C_BORDE);

            $esPar = $idx % 2 === 1;
            $esPar
                ? $pdf->SetFillColor(...self::C_ZEBRA)
                : $pdf->SetFillColor(255, 255, 255);

            $x = self::X;
            $yFila = $pdf->GetY();

            foreach ($textos as $i => $texto) {
                $pdf->MultiCell(
                    self::COLS[$i],
                    $altura,
                    (string) $texto,
                    1,
                    $aligns[$i],
                    true,
                    0,
                    $x,
                    $yFila,
                    true,
                    0,
                    false,
                    true,
                    $altura,
                    'T'
                );
                $x += self::COLS[$i];
            }

            $pdf->SetY($yFila + $altura);
        }

        /* ---------------- TOTALES ---------------- */

        if ($pdf->GetY() > 297 - 10 - 75) {
            $pdf->AddPage();
        }

        $subtotal    = (float) $factura->detalles->sum(fn($d) => (float) $d->sub_total);
        $descuento   = (float) ($factura->descuento_adicional ?? 0);
        $total       = (float) $factura->monto_total;
        $giftCard    = (float) ($factura->monto_gift_card ?? 0);
        $montoPagar  = $total - $giftCard;
        $baseCredito = (float) $factura->monto_total_sujeto_iva;

        $yTotales = $pdf->GetY() + 2;
        $xLabel   = self::X + self::COLS[0] + self::COLS[1] + self::COLS[2] + self::COLS[3];
        $wLabel   = self::COLS[4] + self::COLS[5];
        $wValor   = self::COLS[6];

        $filas = [
            ['SUBTOTAL Bs', $subtotal, false],
            ['DESCUENTO Bs', $descuento, false],
            ['TOTAL Bs', $total, false],
            ['MONTO GIFT CARD Bs', $giftCard, false],
            ['MONTO A PAGAR Bs', $montoPagar, true],
            ['IMPORTE BASE CRÉDITO FISCAL Bs', $baseCredito, true],
        ];

        $yy = $yTotales;

        $pdf->SetDrawColor(...self::C_BORDE);

        foreach ($filas as [$label, $valor, $destacada]) {

            if ($destacada) {
                $pdf->SetFillColor(...self::C_PRIM_SOFT);
                $pdf->SetTextColor(...self::C_PRIMARIO);
                $pdf->SetFont('helvetica', 'B', 7);
            } else {
                $pdf->SetFillColor(...self::C_FONDO);
                $pdf->SetTextColor(...self::C_TEXTO);
                $pdf->SetFont('helvetica', '', 7);
            }

            $pdf->SetXY($xLabel, $yy);
            $pdf->Cell($wLabel, 5, $label, 1, 0, 'R', true);
            $pdf->Cell($wValor, 5, $this->n($valor), 1, 0, 'R', true);
            $yy += 5;
        }

        // Monto en letras (izquierda)
        $pdf->SetTextColor(...self::C_TEXTO);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetXY(self::X + 2, $yTotales + 10);
        $pdf->MultiCell(
            $xLabel - self::X - 8,
            5,
            'Son: ' . $this->montoEnLetras($montoPagar),
            0,
            'L'
        );

        /* ---------------- PIE + QR ---------------- */

        $yPie = $yy + 10;

        $pdf->SetDrawColor(...self::C_BORDE);
        $pdf->Line(self::X, $yPie - 4, self::X + self::ANCHO, $yPie - 4);

        $pdf->SetTextColor(...self::C_MUTED);
        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetXY(self::X, $yPie);
        $pdf->MultiCell(
            158,
            4,
            'ESTA FACTURA CONTRIBUYE AL DESARROLLO DEL PAÍS, EL USO ILÍCITO SERÁ SANCIONADO PENALMENTE DE ACUERDO A LEY',
            0,
            'C',
            false,
            1
        );

        $pdf->Ln(2);
        $pdf->SetX(self::X);
        $pdf->MultiCell(158, 4, (string) $factura->leyenda, 0, 'C', false, 1);

        $pdf->Ln(3);
        $pdf->SetFont('helvetica', 'I', 7);
        $pdf->SetX(self::X);
        $pdf->MultiCell(
            158,
            4,
            '“Este documento es la Representación Gráfica de un Documento Fiscal Digital emitido en una modalidad de facturación en línea”',
            0,
            'C',
            false,
            1
        );

        $pdf->write2DBarcode(
            $this->urlQr($factura),
            'QRCODE,M',
            172,
            $yPie - 2,
            28,
            28,
            ['border' => 0, 'padding' => 0, 'fgcolor' => [0, 0, 0], 'bgcolor' => false],
            'N'
        );


        /* ---------------- MARCA ANULADO new ---------------- */
        if ($anulada) {
            $this->marcaAnulado($pdf);
        }
        /* ---------------- GUARDAR ---------------- */

        $carpeta = public_path('facturas/' . $factura->id_company);

        if (!File::exists($carpeta)) {
            File::makeDirectory($carpeta, 0755, true);
        }

        $nombreArchivo = sprintf(
            'factura-%s-%s.pdf',
            $factura->numero_factura,
            substr((string) $factura->cuf, 0, 8)
        );

        $ruta = $carpeta . DIRECTORY_SEPARATOR . $nombreArchivo;

        $pdf->Output($ruta, 'F');

        if ($logo && $logo['temp'] && File::exists($logo['path'])) {
            File::delete($logo['path']);
        }

        $rutaRelativa = 'facturas/' . $factura->id_company . '/' . $nombreArchivo;

        return [
            'url'      => asset($rutaRelativa),
            'base64'   => base64_encode(file_get_contents($ruta)),
            'filename' => $nombreArchivo,
            'path'     => $rutaRelativa,
        ];
    }

    /* ============================================================
     |  LOGO DE LA EMPRESA
     |  BD: logo_empresa/xxx.webp → storage/app/public/logo_empresa/xxx.webp
     * ============================================================ */
    private function resolverLogo(FacturaCabecera $f): ?array
    {
        try {
            $empresa = Company::where('id_company', $f->id_company)->first();

            if (!$empresa || empty($empresa->logo)) {
                return null;
            }

            $ruta = storage_path('app/public/' . ltrim($empresa->logo, '/'));

            if (!File::exists($ruta)) {
                return null;
            }

            // TCPDF no maneja bien webp: se convierte a PNG temporal
            if (strtolower(pathinfo($ruta, PATHINFO_EXTENSION)) === 'webp') {

                if (!function_exists('imagecreatefromwebp')) {
                    return null;
                }

                $img = @imagecreatefromwebp($ruta);

                if (!$img) {
                    return null;
                }

                imagealphablending($img, false);
                imagesavealpha($img, true);

                $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'logo_' . uniqid() . '.png';

                imagepng($img, $tmp);
                imagedestroy($img);

                return ['path' => $tmp, 'temp' => true];
            }

            return ['path' => $ruta, 'temp' => false];
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /* ============================================================
     |  ENCABEZADO (logo + emisor izquierda / NIT-factura-autorización derecha)
     * ============================================================ */
    private function dibujarEncabezado(TCPDF $pdf, FacturaCabecera $f, ?array $logo): float
    {
        $y0 = 10;

        /* ---- Derecha ---- */
        $xR = 125;
        $wLabel = 32;
        $wValor = 53;

        $filasDer = [
            ['NIT', $f->nit_emisor],
            ['FACTURA N°', $f->numero_factura],
        ];

        $pdf->SetXY($xR, $y0);

        foreach ($filasDer as [$label, $valor]) {
            $pdf->SetX($xR);
            $pdf->SetTextColor(...self::C_PRIMARIO);
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->Cell($wLabel, 5, $label, 0, 0);
            $pdf->SetTextColor(...self::C_TEXTO);
            $pdf->SetFont('helvetica', '', 8);
            $pdf->Cell($wValor, 5, (string) $valor, 0, 1);
        }

        $yAut = $pdf->GetY();
        $pdf->SetTextColor(...self::C_PRIMARIO);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetXY($xR, $yAut);
        $pdf->Cell($wLabel, 5, 'CÓD. AUTORIZACIÓN', 0, 0);
        $pdf->SetTextColor(...self::C_TEXTO);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->MultiCell($wValor, 4, (string) $f->cuf, 0, 'L', false, 1, $xR + $wLabel, $yAut);

        $yDerecha = $pdf->GetY();

        /* ---- Logo ---- */
        $xTxt = self::X;
        $wTxt = 105;

        if ($logo) {
            $pdf->Image(
                $logo['path'],
                self::X,
                $y0,
                26,
                24,
                '',
                '',
                '',
                true,
                300,
                '',
                false,
                false,
                0,
                'CM',
                false,
                false
            );

            $xTxt = self::X + 29;
            $wTxt = 120 - $xTxt;
        }

        /* ---- Emisor ---- */
        $sucursal = (int) $f->codigo_sucursal === 0
            ? 'CASA MATRIZ'
            : 'Sucursal No. ' . $f->codigo_sucursal;

        $pdf->SetTextColor(...self::C_TEXTO);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->MultiCell($wTxt, 5, (string) $f->razon_social_emisor, 0, 'C', false, 1, $xTxt, $y0);

        $pdf->SetTextColor(...self::C_PRIMARIO);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetX($xTxt);
        $pdf->Cell($wTxt, 5, $sucursal, 0, 1, 'C');

        $pdf->SetTextColor(...self::C_MUTED);
        $pdf->SetFont('helvetica', '', 8);

        $pdf->SetX($xTxt);
        $pdf->Cell($wTxt, 4.5, 'No. Punto de Venta ' . $f->codigo_punto_venta, 0, 1, 'C');

        $pdf->MultiCell($wTxt, 4.5, (string) $f->direccion, 0, 'C', false, 1, $xTxt);

        if (!empty($f->telefono)) {
            $pdf->SetX($xTxt);
            $pdf->Cell($wTxt, 4.5, 'Teléfono: ' . $f->telefono, 0, 1, 'C');
        }

        $pdf->SetX($xTxt);
        $pdf->Cell($wTxt, 4.5, (string) $f->municipio, 0, 1, 'C');

        // el logo también ocupa altura
        $yLogo = $logo ? $y0 + 24 : 0;

        return max($yDerecha, $pdf->GetY(), $yLogo);
    }

    /* ============================================================
     |  MARCA DE AGUA
     * ============================================================ */
    private function marcaDeAgua(TCPDF $pdf, float $y): void
    {
        $pdf->SetAlpha(0.12);
        $pdf->SetTextColor(...self::C_PRIMARIO);
        $pdf->SetFont('helvetica', 'B', 52);
        $pdf->SetXY(self::X, $y);
        $pdf->Cell(self::ANCHO, 22, 'SIN VALOR LEGAL', 0, 0, 'C');
        $pdf->SetAlpha(1);
    }

    /* ============================================================
     |  FILA DE DATOS (2 columnas)
     * ============================================================ */
    private function filaDato(TCPDF $pdf, float $y, string $l1, $v1, string $l2, $v2): void
    {
        $pdf->SetXY(self::X + 3, $y);
        $pdf->SetTextColor(...self::C_MUTED);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(38, 5, $l1, 0, 0);
        $pdf->SetTextColor(...self::C_TEXTO);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(55, 5, (string) $v1, 0, 0, 'L', false, '', 1);

        $pdf->SetX(125);
        $pdf->SetTextColor(...self::C_MUTED);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(28, 5, $l2, 0, 0, 'R');
        $pdf->SetTextColor(...self::C_TEXTO);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(35, 5, (string) $v2, 0, 0, 'L', false, '', 1);
    }

    /* ============================================================
     |  CABECERA DE LA TABLA
     * ============================================================ */
    private function cabeceraTabla(TCPDF $pdf): void
    {
        $titulos = [
            "CÓDIGO\nPRODUCTO /\nSERVICIO",
            'CANTIDAD',
            "UNIDAD DE\nMEDIDA",
            'DESCRIPCIÓN',
            "PRECIO\nUNITARIO",
            'DESCUENTO',
            'SUBTOTAL',
        ];

        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetFillColor(...self::C_PRIMARIO);
        $pdf->SetDrawColor(...self::C_PRIMARIO);
        $pdf->SetTextColor(255, 255, 255);

        $x = self::X;
        $y = $pdf->GetY();

        foreach ($titulos as $i => $t) {
            $pdf->MultiCell(
                self::COLS[$i],
                14,
                $t,
                1,
                'C',
                true,
                0,
                $x,
                $y,
                true,
                0,
                false,
                true,
                14,
                'M'
            );
            $x += self::COLS[$i];
        }

        $pdf->SetY($y + 14);
    }

    /* ============================================================
     |  ALTURA DE FILA
     * ============================================================ */
    private function alturaFila(TCPDF $pdf, array $textos): float
    {
        $max = 1;

        foreach ($textos as $i => $texto) {
            $max = max($max, $pdf->getNumLines((string) $texto, self::COLS[$i]));
        }

        return $max * 4.2 + 1.5;
    }

    /* ============================================================
     |  URL DEL QR (SIAT)
     * ============================================================ */
    private function urlQr(FacturaCabecera $f): string
    {
        $base = config(
            'services.siat.qr_url',
            'https://pilotosiat.impuestos.gob.bo/consulta/QR'
        );

        return sprintf(
            '%s?nit=%s&cuf=%s&numero=%s',
            $base,
            $f->nit_emisor,
            $f->cuf,
            $f->numero_factura
        );
    }

    /* ============================================================
     |  FORMATO NÚMERO
     * ============================================================ */
    private function n($valor): string
    {
        return number_format((float) $valor, 2, '.', ',');
    }

    /* ============================================================
     |  MONTO EN LETRAS: "Veinte 00/100 Bolivianos"
     * ============================================================ */
    private function montoEnLetras(float $monto): string
    {
        $monto   = round($monto, 2);
        $entero  = (int) floor($monto);
        $decimal = (int) round(($monto - $entero) * 100);

        $texto = $this->numeroALetras($entero);

        return ucfirst($texto) . ' ' . str_pad((string) $decimal, 2, '0', STR_PAD_LEFT) . '/100 Bolivianos';
    }

    private function numeroALetras(int $n): string
    {
        static $u = [
            'cero',
            'uno',
            'dos',
            'tres',
            'cuatro',
            'cinco',
            'seis',
            'siete',
            'ocho',
            'nueve',
            'diez',
            'once',
            'doce',
            'trece',
            'catorce',
            'quince',
            'dieciséis',
            'diecisiete',
            'dieciocho',
            'diecinueve',
            'veinte',
            'veintiuno',
            'veintidós',
            'veintitrés',
            'veinticuatro',
            'veinticinco',
            'veintiséis',
            'veintisiete',
            'veintiocho',
            'veintinueve',
        ];

        static $d = [
            3 => 'treinta',
            4 => 'cuarenta',
            5 => 'cincuenta',
            6 => 'sesenta',
            7 => 'setenta',
            8 => 'ochenta',
            9 => 'noventa',
        ];

        static $c = [
            1 => 'ciento',
            2 => 'doscientos',
            3 => 'trescientos',
            4 => 'cuatrocientos',
            5 => 'quinientos',
            6 => 'seiscientos',
            7 => 'setecientos',
            8 => 'ochocientos',
            9 => 'novecientos',
        ];

        if ($n < 30) {
            return $u[$n];
        }

        if ($n < 100) {
            $dec = intdiv($n, 10);
            $uni = $n % 10;

            return $d[$dec] . ($uni ? ' y ' . $u[$uni] : '');
        }

        if ($n === 100) {
            return 'cien';
        }

        if ($n < 1000) {
            $cen   = intdiv($n, 100);
            $resto = $n % 100;

            return $c[$cen] . ($resto ? ' ' . $this->numeroALetras($resto) : '');
        }

        if ($n < 1_000_000) {
            $miles = intdiv($n, 1000);
            $resto = $n % 1000;

            $txt = $miles === 1
                ? 'mil'
                : $this->apocope($this->numeroALetras($miles)) . ' mil';

            return $txt . ($resto ? ' ' . $this->numeroALetras($resto) : '');
        }

        $millones = intdiv($n, 1_000_000);
        $resto    = $n % 1_000_000;

        $txt = $millones === 1
            ? 'un millón'
            : $this->apocope($this->numeroALetras($millones)) . ' millones';

        return $txt . ($resto ? ' ' . $this->numeroALetras($resto) : '');
    }

    private function apocope(string $texto): string
    {
        $texto = preg_replace('/veintiuno$/u', 'veintiún', $texto);

        return preg_replace('/uno$/u', 'un', $texto);
    }

    //new funcion para la marca de agua de anulacion 
    private function marcaAnulado(TCPDF $pdf): void
    {
        $paginas = $pdf->getNumPages();

        for ($p = 1; $p <= $paginas; $p++) {
            $pdf->setPage($p);

            $pdf->StartTransform();
            $pdf->Rotate(45, 105, 148); // centro de la hoja A4

            $pdf->SetAlpha(0.25);
            $pdf->SetTextColor(220, 38, 38);
            $pdf->SetFont('helvetica', 'B', 90);
            $pdf->SetXY(self::X, 136);
            $pdf->Cell(self::ANCHO, 24, 'ANULADO', 0, 0, 'C');
            $pdf->SetAlpha(1);

            $pdf->StopTransform();
        }
    }

    private function obtenerDescripcionUnidad(
    int $codigoClasificador,
    string $idCompany
): string {
    $ruta = storage_path(
        "app/private/siat/{$idCompany}/parametros/unidades.json"
    );

    if (!File::exists($ruta)) {
        return (string) $codigoClasificador;
    }

    $json = json_decode(File::get($ruta), true);

    foreach ($json['data'] ?? [] as $unidad) {

        if ((int) ($unidad['codigoClasificador'] ?? 0) === $codigoClasificador) {
            return (string) ($unidad['descripcion'] ?? $codigoClasificador);
        }
    }

    return (string) $codigoClasificador;
}
}
