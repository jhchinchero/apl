<?php

namespace App\Domains\Facturacion\Services\Siat\Sincronizacion;

use Exception;
use Throwable;

use Illuminate\Support\Facades\Log;

use App\Domains\Facturacion\Services\Siat\Core\SiatConfig;
use App\Domains\Facturacion\Services\Siat\Core\SiatSoapResponseHelper;
use App\Domains\Facturacion\Services\Siat\Core\SiatServiceFactory;
use App\Domains\Facturacion\Services\Siat\Catalogos\SiatCatalogoJsonService;

class SiatSincronizacionService
{
    private const SERVICIOS = [
        'catalogos' => [
            'actividades'         => 'sincronizarActividades',
            'productos_servicios' => 'sincronizarListaProductosServicios',
            'leyendas'            => 'sincronizarListaLeyendasFactura',
            'mensajes'            => 'sincronizarListaMensajesServicios',
        ],
        'parametros' => [
            'eventos'              => 'sincronizarParametricaEventosSignificativos',
            'motivos_anulacion'    => 'sincronizarParametricaMotivoAnulacion',
            'documentos_identidad' => 'sincronizarParametricaTipoDocumentoIdentidad',
            'documentos_sector'    => 'sincronizarParametricaTipoDocumentoSector',
            'tipos_emision'        => 'sincronizarParametricaTipoEmision',
            'tipos_habitacion'     => 'sincronizarParametricaTipoHabitacion',
            'metodos_pago'         => 'sincronizarParametricaTipoMetodoPago',
            'monedas'              => 'sincronizarParametricaTipoMoneda',
            'tipos_punto_venta'    => 'sincronizarParametricaTipoPuntoVenta',
            'tipos_factura'        => 'sincronizarParametricaTiposFactura',
            'unidades'             => 'sincronizarParametricaUnidadMedida',
            'paises'               => 'sincronizarParametricaPaisOrigen',
        ],
    ];

    public function __construct(
        private SiatCatalogoJsonService $json
    ) {}

    public function sincronizarTodo(
        string $idCompany,
        string $cuis,
        int $codigoSucursal,
        int $codigoPuntoVenta
    ): array {

        // La sincronización puede tardar (16 llamadas SOAP seguidas).
        set_time_limit(300);

        $siat   = SiatServiceFactory::forCompany($idCompany);
        $config = $siat->config();

        // TODO: mover a config/siat.php según $config->codigo_ambiente
        // (1 = producción, 2 = pruebas/piloto).
        $wsdl = config(
            'siat.wsdl.sincronizacion',
            'https://pilotosiatservicios.impuestos.gob.bo/v2/FacturacionSincronizacion?wsdl'
        );

        $soap = $siat->soap($wsdl);

        $baseParams = SiatConfig::sincronizacion(
            $config,
            $codigoSucursal,
            $codigoPuntoVenta,
            $cuis
        );

        $resultado = ['catalogos' => [], 'parametros' => []];

        $total = 0;
        $exitosos = 0;
        $fallidos = 0;

        foreach (self::SERVICIOS as $tipo => $items) {

            foreach ($items as $nombre => $method) {

                $total++;

                try {

                    $response = $soap->call($method, [
                        'SolicitudSincronizacion' => $baseParams,
                    ]);

                    if (!SiatSoapResponseHelper::transaccion($response)) {
                        throw new Exception(
                            SiatSoapResponseHelper::mensaje($response)
                                ?? "SIAT rechazó {$nombre}."
                        );
                    }

                    $data = SiatSoapResponseHelper::extractList($response);

                    $meta = [
                        'codigo_sucursal'    => $codigoSucursal,
                        'codigo_punto_venta' => $codigoPuntoVenta,
                        'cuis'               => $cuis,
                        'codigo_ambiente'    => $config->codigo_ambiente,
                        'codigo_sistema'     => $config->codigo_sistema,
                        'servicio'           => $method,
                    ];

                    $guardado = $tipo === 'catalogos'
                        ? $this->json->guardarCatalogo($idCompany, $nombre, $data, $meta)
                        : $this->json->guardarParametro($idCompany, $nombre, $data, $meta);

                    $resultado[$tipo][$nombre] = [
                        'success'          => true,
                        'estado'           => 'OK',
                        'cantidad'         => count($data),
                        'cambio_detectado' => $guardado['cambio_detectado'],
                        'hash'             => $guardado['hash'],
                        'fecha'            => now()->toDateTimeString(),
                    ];

                    $exitosos++;

                } catch (Throwable $e) {

                    Log::warning("SIAT sincronización falló: {$nombre}", [
                        'empresa' => $idCompany,
                        'servicio' => $method,
                        'error'   => $e->getMessage(),
                    ]);

                    $resultado[$tipo][$nombre] = [
                        'success'  => false,
                        'estado'   => 'ERROR',
                        'cantidad' => 0,
                        'message'  => $e->getMessage(),
                        'fecha'    => now()->toDateTimeString(),
                    ];

                    $fallidos++;
                }
            }
        }

        $estadoGeneral = 'OK';

        if ($fallidos === $total) {
            $estadoGeneral = 'ERROR';
        } elseif ($fallidos > 0) {
            $estadoGeneral = 'PARCIAL';
        }

        $resumen = [
            'total'    => $total,
            'exitosos' => $exitosos,
            'fallidos' => $fallidos,
        ];

        $this->json->guardarEstado($idCompany, [
            'estado_general' => $estadoGeneral,
            'resumen' => $resumen + [
                'porcentaje' => $total > 0
                    ? round(($exitosos / $total) * 100, 2)
                    : 0,
            ],
            'catalogos'  => $resultado['catalogos'],
            'parametros' => $resultado['parametros'],
        ]);

        return [
            'success'        => $fallidos === 0,
            'estado_general' => $estadoGeneral,
            'resumen'        => $resumen,
            'catalogos'      => $resultado['catalogos'],
            'parametros'     => $resultado['parametros'],
        ];
    }
}