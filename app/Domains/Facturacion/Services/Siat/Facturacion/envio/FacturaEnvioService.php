<?php

namespace App\Domains\Facturacion\Services\Siat\Facturacion\envio;

use App\Domains\Empresa\Models\Branch;
use App\Domains\Empresa\Models\PointOfSale;
use Exception;
use Illuminate\Support\Facades\Http;

use App\Helpers\SiatResponseCodigoEstadoHerper;

class FacturaEnvioService
{
    public function enviar(
        string $gzip,
        string $hashArchivo,//si
        string $cuis,
        string $cufd,
        $config,
        $branch,
        $pointOfSale
    ): array {

        try {

            // =====================================================
            // URL SIAT PILOTO
            // =====================================================

            $url =
                'https://pilotosiatservicios.impuestos.gob.bo/v2/ServicioFacturacionCompraVenta?wsdl';

            // =====================================================
            // ARCHIVO BASE64
            // =====================================================

           

            // =====================================================
            // FECHA ENVIO
            // =====================================================


            $codigoAmbiente = $config->codigo_ambiente;
            $codigoDocumentoSector = 1;
            $codigoEmision = 1;
            $codigoModalidad = 1;
            $codigoPuntoVenta = $pointOfSale->codigo;
            $codigoSistema = $config->codigo_sistema;
            $codigoSucursal = $branch->codigo;
            $cufd = $cufd;
            $cuis = $cuis;
            $nit = $config->nit;
            $archivoBase64 = base64_encode($gzip);
            $fechaEnvio = now()->format('Y-m-d\TH:i:s.v');
            $hashArchivo = $hashArchivo;//si
            $tipoFacturaDocumento = 1;

            // =====================================================
            // SOAP XML
            // =====================================================

            $soapBody = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>

            <soapenv:Envelope
                xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                xmlns:ser="https://siat.impuestos.gob.bo/">

                <soapenv:Header/>

                <soapenv:Body>

                    <ser:recepcionFactura>

                        <SolicitudServicioRecepcionFactura>

                            <codigoAmbiente>{$codigoAmbiente}</codigoAmbiente>
                            <codigoDocumentoSector>{$codigoDocumentoSector}</codigoDocumentoSector>
                            <codigoEmision>{$codigoEmision}</codigoEmision>
                            <codigoModalidad>{$codigoModalidad}</codigoModalidad>
                            <codigoPuntoVenta>{$codigoPuntoVenta}</codigoPuntoVenta>
                            <codigoSistema>{$codigoSistema}</codigoSistema>
                            <codigoSucursal>{$codigoSucursal}</codigoSucursal>
                            <cufd>{$cufd}</cufd>
                            <cuis>{$cuis}</cuis>
                            <nit>{$nit}</nit>
                            <archivo>{$archivoBase64}</archivo>

                            <fechaEnvio>{$fechaEnvio}</fechaEnvio>

                            <hashArchivo>{$hashArchivo}</hashArchivo>

                            <tipoFacturaDocumento>{$tipoFacturaDocumento}</tipoFacturaDocumento>

                        </SolicitudServicioRecepcionFactura>

                    </ser:recepcionFactura>

                </soapenv:Body>

            </soapenv:Envelope>
            XML;

            // =====================================================
            // ENVIAR AL SIAT
            // =====================================================

            $response = Http::withHeaders([

                'Content-Type' => 'text/xml;charset=UTF-8',
                'SOAPAction' => ''

            ])->withBody(
                $soapBody,
                'text/xml'
            )->post($url);

            // =====================================================
            // RESPUESTA
            // =====================================================

             // =====================================================
        // HTTP
        // =====================================================

        if ($response->status() !== 200) {

            return [
                'conexionOk' => true,
                'httpCode' => $response->status(),
                'aceptada' => false,
                'message' => 'Error en el servidor SIAT.',
                'respuestaXml' => $response->body(),
            ];
        }

        if (!$response->body()) {

            return [
                'conexionOk' => true,
                'httpCode' => 200,
                'aceptada' => false,
                'message' => 'SIAT no devolvió respuesta.',
                'respuestaXml' => null,
            ];
        }

        // =====================================================
        // LEER RESPUESTA SOAP
        // =====================================================

        $xml = simplexml_load_string($response->body());

        if ($xml === false) {

            return [
                'conexionOk' => true,
                'httpCode' => 200,
                'aceptada' => false,
                'message' => 'No se pudo interpretar la respuesta SOAP de SIAT.',
                'respuestaXml' => $response->body(),
            ];
        }

        $codigoEstado = (int) (
            $xml->xpath('//codigoEstado')[0] ?? 0
        );

        $codigoDescripcion = (string) (
            $xml->xpath('//codigoDescripcion')[0] ?? ''
        );

        $codigoRecepcion = (string) (
            $xml->xpath('//codigoRecepcion')[0] ?? ''
        );

        $transaccion = filter_var(
            (string) ($xml->xpath('//transaccion')[0] ?? 'false'),
            FILTER_VALIDATE_BOOLEAN
        );

        // =====================================================
        // DETERMINAR RESULTADO
        // =====================================================

        $aceptada = (
            $codigoEstado === 908 &&
            $transaccion === true
        );

        return [
            'conexionOk' => true,
            'httpCode' => 200,

            'codigoEstado' => $codigoEstado,
            'codigoDescripcion' => $codigoDescripcion,
            'codigoRecepcion' => $codigoRecepcion,
            'transaccion' => $transaccion,

            'aceptada' => $aceptada,

            'respuestaXml' => $response->body(),
        ];
        
        } catch (Exception $e) {

           return [
            'conexionOk' => false,
            'httpCode' => null,
            'aceptada' => false,
            'message' => $e->getMessage(),
            'respuestaXml' => null,
        ];
        }
    }
}
