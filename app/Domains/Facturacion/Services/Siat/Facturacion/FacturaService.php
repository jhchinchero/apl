<?php



namespace App\Domains\Facturacion\Services\Siat\Facturacion;



use App\Domains\Empresa\Models\PointOfSale;
use App\Domains\Empresa\Models\Branch;
use App\Domains\Facturacion\Services\Siat\Core\SiatService;
use App\Domains\Facturacion\Services\Siat\Cufd\CufdService;
use App\Domains\Facturacion\Services\Siat\Cuis\CuisService;
use App\Domains\Facturacion\Services\Siat\Core\SiatServiceFactory;

use App\Domains\Facturacion\Services\Siat\Facturacion\Cuf\GenerarCuf;
use App\Domains\Facturacion\Services\Siat\Facturacion\xml\GenerarXml;
use App\Domains\Facturacion\Services\Siat\Facturacion\envio\FacturaEnvioService;
use Illuminate\Support\Facades\Storage;
use App\Domains\Facturacion\Services\FacturaDbService;
use App\Domains\Facturacion\Services\FacturaPdfService;

use Carbon\Carbon;

class FacturaService
{
    private SiatService $siatService;
    private CufdService $cufdService;
    private CuisService $cuisService;
    private GenerarCuf $generarCuf;
    private GenerarXml $generarXml;
    private FacturaEnvioService $facturaEnvioService;
    private FacturaDbService $facturaDbService;
    private FacturaPdfService $facturaPdfService;

    public function __construct(
        SiatService $siatService,
        CufdService $cufdService,
        CuisService $cuisService,
        GenerarCuf $generarCuf,
        GenerarXml $generarXml,
        FacturaEnvioService $facturaEnvioService,
        FacturaDbService $facturaDbService,
        FacturaPdfService $facturaPdfService
    ) {
        $this->siatService = $siatService;
        $this->cufdService = $cufdService;
        $this->cuisService = $cuisService;
        $this->generarCuf = $generarCuf;
        $this->generarXml = $generarXml;
        $this->facturaEnvioService = $facturaEnvioService;
        $this->facturaDbService = $facturaDbService;
        $this->facturaPdfService= $facturaPdfService;
    }

    public function procesar(
        array $factura,
        PointOfSale $pointOfSale,
        $companyId,
        Branch $branch
    ) {
        try {

            $fechaEmision = Carbon::now('America/La_Paz')
                ->setMicrosecond(0);

            // 1. Obtener configuración
            $siatConfig = SiatServiceFactory::forCompany($companyId);
            // 2. Obtener y validar CUFD
            $serviceCufd = new CufdService($siatConfig);
            $cufd = $serviceCufd->solicitar($pointOfSale);

            // Generar CUF
            /*   $cuf = $this->generarCuf->generarCuf(
                $siatConfig->config(),
                $cufd,
                $pointOfSale->code,
                1
            ); */

            $numeroFactura = $this->obtenerSiguienteNumeroFactura(
                $companyId,
                $pointOfSale
            );


            $cuf = $this->generarCuf->generarCuf(
                $siatConfig->config(),
                $cufd,
                $branch->codigo,
                $pointOfSale->codigo,
                $numeroFactura,
                $fechaEmision
            );

            $serviceCuis = new CuisService($siatConfig);
            $cuis = $serviceCuis->solicitar($pointOfSale);

            /* 
            return [
                    'success' => true,
                    'message' =>null,
                    'cuis'=>$cuis,
                ]; */
            //array $factura, $siatConfig, $cufd_, $numero_factura, $cuf, $codigo_sucursal

            $xml = $this->generarXml->generarXml(
                $factura,
                $siatConfig->config(),
                $cufd->codigo,
                $numeroFactura,
                $cuf,
                $branch,
                $pointOfSale,
                $fechaEmision
            );

            if (!$xml) {
                return [
                    'success' => false,
                    'message' => 'No fue posible generar el XML.',
                ];
            }

            // =========================================================
            // COMPRIMIR XML EN MEMORIA
            // =========================================================

            $gzip = gzencode(
                $xml,
                9,
                ZLIB_ENCODING_GZIP
            );

            if ($gzip === false) {
                return [
                    'success' => false,
                    'message' => 'No fue posible comprimir el XML en GZIP.',
                ];
            }

            // =========================================================
            // HASH DEL GZIP
            // =========================================================

            $hashArchivo = hash(
                'sha256',
                $gzip
            );

            // =========================================================
            // ENVIAR A SIAT
            // =========================================================

            $resp = $this->facturaEnvioService->enviar(
                $gzip,
                $hashArchivo,
                $cuis->codigo,
                $cufd->codigo,
                $siatConfig->config(),
                $branch,
                $pointOfSale
            );

            // =========================================================
            // ERROR DE CONEXIÓN
            // =========================================================

            if (!$resp['conexionOk']) {

                return [
                    'success' => false,
                    'message' => $resp['message']
                        ?? 'Error de conexión con SIAT.',
                    'data' => $resp,
                ];
            }

            // =========================================================
            // SIAT NO RESPONDIÓ HTTP 200
            // =========================================================

            if (($resp['httpCode'] ?? 0) !== 200) {

                return [
                    'success' => false,
                    'message' => 'Error en el servidor SIAT.',
                    'data' => $resp,
                ];
            }

            // =========================================================
            // FACTURA RECHAZADA / NO ACEPTADA
            // =========================================================

            if (($resp['aceptada'] ?? false) !== true) {

                return [
                    'resp' => $resp,
                    'success' => false,
                    'message' => $resp['codigoDescripcion']
                        ?? 'La factura no fue aceptada por SIAT.',
                    'data' => [
                        'codigoEstado' => $resp['codigoEstado'] ?? null,
                        'codigoDescripcion' => $resp['codigoDescripcion'] ?? null,
                        'codigoRecepcion' => $resp['codigoRecepcion'] ?? null,
                    ],
                ];
            }

            // =========================================================
            // DESDE AQUÍ SIAT ACEPTÓ LA FACTURA
            // =========================================================

            // =========================================================
            // FECHA / DIRECTORIO
            // =========================================================

            $fechaArchivo = now()->format('Y-m-d_H-i-s');

            $anio = now()->format('Y');
            $mes = now()->format('m');

            $directorio = sprintf(
                'facturas_xml/%s/%s/%s',
                $companyId,
                $anio,
                $mes
            );

            // =========================================================
            // NOMBRE
            // =========================================================

            $nombreBase = sprintf(
                'factura_%d_%s_%s',
                $numeroFactura,
                $fechaArchivo,
                $cuf
            );

            $nombreXml = $nombreBase . '.xml';
            $rutaXml = $directorio . '/' . $nombreXml;

            $nombreGzip = $nombreBase . '.xml.gz';
            $rutaGzip = $directorio . '/' . $nombreGzip;

            // =========================================================
            // GUARDAR XML
            // =========================================================

            Storage::disk('local')->put(
                $rutaXml,
                $xml
            );

            // =========================================================
            // GUARDAR GZIP
            // =========================================================

            Storage::disk('local')->put(
                $rutaGzip,
                $gzip
            );

            // =========================================================
            // AQUÍ GUARDAS FACTURA EN BD
            // =========================================================

            // $facturaCabecera = ...
            // $facturaDetalle = ...

            /*
        |--------------------------------------------------------------------------
        | GUARDAR EN BASE DE DATOS
        |--------------------------------------------------------------------------
        */

         /*    Log::info('Guardando factura en DB', [
                'company_id'    => $companyId,
                'pos_id'        => $pointOfSale->id,
                'numeroFactura' => $numeroFactura,
                'xml_es_string' => is_string($xml),
            ]); */

            try {

                $facturaGuardada = $this->facturaDbService->guardarDesdeXml(
                    (string) $xml,
                    (string) $companyId,
                    $pointOfSale->id
                );
            } catch (\Throwable $e) {

                report($e);

                return [
                    'success'           => false,
                    'aceptada'          => true,
                    'siatAceptada'      => true,
                    'persistenciaLocal' => false,
                    'message'           => 'SIAT aceptó la factura, pero ocurrió un error al guardarla en la base de datos.',
                    'error'             => $e->getMessage(),
                    'codigoEstado'      => $resp['codigoEstado'] ?? null,
                    'codigoRecepcion'   => $resp['codigoRecepcion'] ?? null,
                    'cuf'               => $cuf,
                    'numeroFactura'     => $numeroFactura,
                    'rutaXml'           => $rutaXml,
                    'rutaGzip'          => $rutaGzip,
                ];
            }

            // PDF: si falla, la factura ya está guardada y no debe marcarse como error
            $pdf = null;

            try {
   

                  $pdf = $this->facturaPdfService->generar($facturaGuardada);

                    // =========================================================
                    // GUARDAR RUTA DEL PDF EN FACTURA_CABECERA
                    // =========================================================

                    if (!empty($pdf['path'])) {

                        $facturaGuardada->pdf_path = $pdf['path'];
                        $facturaGuardada->save();
                    }
            } catch (\Throwable $e) {
                report($e);
            }

            return [
                'success'           => true,
                'aceptada'          => true,
                'siatAceptada'      => true,
                'persistenciaLocal' => true,
                'message'           => 'Factura emitida y registrada correctamente.',
       /*          'codigoEstado'      => $resp['codigoEstado'] ?? null,
                'codigoDescripcion' => $resp['codigoDescripcion'] ?? null,
                'codigoRecepcion'   => $resp['codigoRecepcion'] ?? null,
                'transaccion'       => $resp['transaccion'] ?? false, */
                'cuf'               => $cuf,
                'nit'               =>$siatConfig->config()->nit,
                'numeroFactura'     => $numeroFactura,
                'factura_id'        => $facturaGuardada->id,
                'rutaXml'           => $rutaXml,
                'rutaGzip'          => $rutaGzip,
                'factura'           => $facturaGuardada,
                'pdf'               => $pdf,
            ];
        } catch (\Throwable $e) {

            report($e);

            return [
                'success' => false,
                'message' => 'Error al procesar factura: ' . $e->getMessage(),
            ];
        }
    }

    private function obtenerSiguienteNumeroFactura(
        string $companyId,
        PointOfSale $pointOfSale
    ): int {

        $anio = now()->format('Y');
        $mes = now()->format('m');

        $directorio = sprintf(
            'facturas_xml/%s/%s/%s',
            $companyId,
            $anio,
            $mes
        );

        if (!Storage::disk('local')->exists($directorio)) {
            return 1;
        }

        $archivos = Storage::disk('local')->files(
            $directorio
        );

        $ultimoNumero = 0;

        foreach ($archivos as $archivo) {

            $nombre = basename($archivo);

            /*
         * Busca:
         *
         * factura_123_2026-09-22_18-42-15_CUF.xml
         *
         * o:
         *
         * factura_123_2026-09-22_18-42-15_CUF.xml.gz
         */

            if (
                preg_match(
                    '/^factura_(\d+)_/',
                    $nombre,
                    $matches
                )
            ) {

                $numero = (int) $matches[1];

                if ($numero > $ultimoNumero) {
                    $ultimoNumero = $numero;
                }
            }
        }

        return $ultimoNumero + 1;
    }
}

/*
 'point_of_sale_id' => $pointOfSale->id,
                    'point_of_sale_code' => $pointOfSale->codigo,
                    'branch_id' => $pointOfSale->branch_id,
                    'company_id' => $pointOfSale->id_company,
                    'siat' => $siat,
                    'cabecera' => $factura['cabecera'],
                    'detalle' => $factura['detalle'],
*/