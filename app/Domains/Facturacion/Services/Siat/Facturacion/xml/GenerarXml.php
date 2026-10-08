<?php

namespace App\Domains\Facturacion\Services\Siat\Facturacion\xml;

use DOMDocument;
use Exception;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use Illuminate\Support\Facades\Storage;




class GenerarXml
{
    // =========================================================
    // GENERAR XML FACTURA
    // =========================================================
    public function generarXml(  array $factura,
        $siatConfig,
        $cufd_,
        $numero_factura,
        $cuf,
        $branch,
        $pointOfSale,
        $fechaEmision): string
    {
        //datos de la empresa
        $nit_emisor = $siatConfig->nit;
        $razon_social = 'SHIRLEY NOEMI VILLCA CUSI';
        $municipio = $branch->municipality;
        $direccion = $branch->address;

        $usuario = 'admin';
        $codigo_Documento_Sector = 1;

        $codigo_sucursal = $branch->codigo;

        //END
        $codigo_punto_venta = $pointOfSale->codigo;

        $c = $factura['cabecera'];

        // PRIMER DETALLE
        //$d = $factura['detalle'][0];

        // =====================================================
        // CAMPOS OPCIONALES
        // =====================================================

        $telefono = $c['telefono'] ?? '';

        $cufd = $cufd_;

        $fechaEmision = $fechaEmision
    ->copy()
    ->setTimezone('America/La_Paz')
    ->format('Y-m-d\TH:i:s.v');

        $descuentoAdicional = $c['descuentoAdicional'] ?? 0;

        $leyenda =  '';

        // =====================================================
        // DETALLE (CORREGIDO: múltiples productos)
        // =====================================================
        $detalleXml = '';

        foreach ($factura['detalle'] as $d) {

            $detalleXml .= <<<XML
                    <detalle>
                    <actividadEconomica>{$d['actividadEconomica']}</actividadEconomica>

                    <codigoProductoSin>{$d['codigoProductoSin']}</codigoProductoSin>

                    <codigoProducto>{$d['codigoProducto']}</codigoProducto>

                    <descripcion>{$d['descripcion']}</descripcion>

                    <cantidad>{$d['cantidad']}</cantidad>

                    <unidadMedida>{$d['unidadMedida']}</unidadMedida>

                    <precioUnitario>{$d['precioUnitario']}</precioUnitario>

                    <montoDescuento>{$d['montoDescuento']}</montoDescuento>

                    <subTotal>{$d['subTotal']}</subTotal>

                    <numeroSerie xsi:nil="true"/>

                    <numeroImei xsi:nil="true"/>

                </detalle>
            XML;
        }

        $total = 0;

        foreach ($factura['detalle'] as &$d) {

            $cantidad = (float) $d['cantidad'];
            $precio = (float) $d['precioUnitario'];
            $descuento = (float) ($d['montoDescuento'] ?? 0);

            $subTotal = ($cantidad * $precio) - $descuento;

            $d['subTotal'] = round($subTotal, 2);

            $total += $d['subTotal'];
        }

        $total = round($total, 2);

        $c['montoTotal'] = $total;
        $c['montoTotalSujetoIva'] = $total;
        $c['montoTotalMoneda'] = $total;

        // =====================================================
        // XML
        // =====================================================

        $xml = <<<XML
        <?xml version="1.0" encoding="UTF-8" standalone="yes"?>

        <facturaElectronicaCompraVenta
            xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
            xsi:noNamespaceSchemaLocation="facturaElectronicaCompraVenta.xsd">

            <cabecera>

                <nitEmisor>{$nit_emisor}</nitEmisor>

                <razonSocialEmisor>{$razon_social}</razonSocialEmisor>

                <municipio>{$municipio}</municipio>

                <telefono>{$telefono}</telefono>

                <numeroFactura>{$numero_factura}</numeroFactura>

                <cuf>{$cuf}</cuf>

                <cufd>{$cufd}</cufd>

                <codigoSucursal>{$codigo_sucursal}</codigoSucursal>

                <direccion>{$direccion}</direccion>

                <codigoPuntoVenta>{$codigo_punto_venta}</codigoPuntoVenta>

                <fechaEmision>{$fechaEmision}</fechaEmision>

                <nombreRazonSocial>{$c['nombreRazonSocial']}</nombreRazonSocial>

                <codigoTipoDocumentoIdentidad>{$c['codigoTipoDocumentoIdentidad']}</codigoTipoDocumentoIdentidad>

                <numeroDocumento>{$c['numeroDocumento']}</numeroDocumento>

                <complemento xsi:nil="true"/>

                <codigoCliente>{$c['codigoCliente']}</codigoCliente>

                <codigoMetodoPago>{$c['codigoMetodoPago']}</codigoMetodoPago>

                <numeroTarjeta xsi:nil="true"/>

                <montoTotal>{$c['montoTotal']}</montoTotal>

                <montoTotalSujetoIva>{$c['montoTotalSujetoIva']}</montoTotalSujetoIva>

                <codigoMoneda>{$c['codigoMoneda']}</codigoMoneda>

                <tipoCambio>{$c['tipoCambio']}</tipoCambio>

                <montoTotalMoneda>{$c['montoTotalMoneda']}</montoTotalMoneda>

                <montoGiftCard>0</montoGiftCard>

                <descuentoAdicional>{$descuentoAdicional}</descuentoAdicional>

                <codigoExcepcion xsi:nil="true"/>

                <cafc xsi:nil="true"/>

                <leyenda>Ley N° 453: El proveedor debe brindar atención sin discriminación, con respeto, calidez y cordialidad a los usuarios y consumidores.</leyenda>

                <usuario>{$usuario}</usuario>

                <codigoDocumentoSector>{$codigo_Documento_Sector}</codigoDocumentoSector>

            </cabecera>

        {$detalleXml}

        </facturaElectronicaCompraVenta>
        XML;

        // =====================================================
        // RETORNAR XML
        // =====================================================
        //return $xml;

        return $this->firmarXml($xml);
    }
    public function firmarXml(string $xml): string
    {
        libxml_use_internal_errors(true);

        $doc = new DOMDocument();
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = false;

        if (!$doc->loadXML(trim($xml))) {

            $errores = [];

            foreach (libxml_get_errors() as $error) {
                $errores[] = trim($error->message);
            }

            throw new Exception(
                'XML inválido: ' . implode(', ', $errores)
            );
        }

        $privateKey = storage_path('app/siat/privada.pem');
        $publicCert = storage_path('app/siat/certificado_limpio.pem');

        $objDSig = new XMLSecurityDSig();

        $objDSig->setCanonicalMethod(
            XMLSecurityDSig::EXC_C14N
        );

        $objDSig->addReference(
            $doc,
            XMLSecurityDSig::SHA256,
            [
                'http://www.w3.org/2000/09/xmldsig#enveloped-signature',
                'http://www.w3.org/2001/10/xml-exc-c14n#'
            ],
            [
                'force_uri' => true
            ]
        );

        $objKey = new XMLSecurityKey(
            XMLSecurityKey::RSA_SHA256,
            [
                'type' => 'private'
            ]
        );

        $objKey->loadKey($privateKey, true);

        $objDSig->sign($objKey);

        $objDSig->add509Cert(
            file_get_contents($publicCert),
            true,
            false
        );

        $objDSig->appendSignature(
            $doc->documentElement
        );

        $xmlFirmado = $doc->saveXML();

        file_put_contents(
            storage_path('app/xml-firmado.xml'),
            $xmlFirmado
        );

        return $xmlFirmado;
    }
}
