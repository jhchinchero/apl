<?php



namespace App\Domains\Facturacion\Services\Siat\Core;


use App\Domains\Facturacion\Models\SiatConfiguracion;
use RuntimeException;

class SiatServiceFactory
{
    /**
     * Crea el servicio SIAT para una empresa.
     */
    public static function forCompany(
        string $idCompany
    ): SiatService {

        $configuracion = SiatConfiguracion::where(
            'id_company',
            $idCompany
        )
        ->where('activo', true)
        ->first();

        if (!$configuracion) {
            throw new RuntimeException(
                "La empresa {$idCompany} no tiene configuración SIAT."
            );
        }

        return new SiatService(
            $configuracion
        );
    }
}

/*
siat_configuraciones

EMP-00001
    nit = 123456789
    codigo_ambiente = 2
    codigo_modalidad = 1
    codigo_sistema = SISTEMA-001
    token_delegado = ********

Entonces cualquier parte de Laravel puede hacer:

$siat = SiatServiceFactory::forCompany(
    auth()->user()->id_company
);


$config = $siat->config();

$config->nit
$config->codigo_ambiente
$config->codigo_modalidad
$config->codigo_sistema


Y para SOAP:

$soap = $siat->soap($wsdl);

Luego:

$respuesta = $soap->call(
    'verificarComunicacion',
    []
);




Porque el WSDL pertenece al servicio que tu aplicación consume, no a la empresa.

Entonces:

EMP-00001
       ↓
siat_configuraciones
       ↓
token / NIT / modalidad / ambiente
       ↓
SiatService
       ↓
SiatSoapClient
       ↓
WSDL del servicio
       ↓
SIAT






7. Y para CUIS/CUFD hay una diferencia

Aquí no debes usar solamente la configuración de empresa.

Por ejemplo:

Empresa EMP-00001

Sucursal PRINCIPAL
codigo = 0

    POS Caja Principal
    codigo = 0
        CUIS
        CUFD

    POS Caja 2
    codigo = 1
        CUIS
        CUFD

Sucursal EL ALTO
codigo = 1

    POS Caja Principal
    codigo = 0
        CUIS
        CUFD

Por eso cuando solicites un CUIS, el flujo debería ser:

Usuario
 ↓
Empresa
 ↓
Sucursal
 ↓
Punto de venta
 ↓
SiatConfiguracion
 ↓
SiatSoapClient
 ↓
SIAT
 ↓
guardar CUIS

Y para CUFD:

Punto de venta
      ↓
CUIS vigente
      ↓
solicitar CUFD
      ↓
guardar CUFD
La arquitectura que te recomiendo
app/
└── Services/
    └── Siat/
        ├── Core/
        │   ├── SiatSoapClient.php      ← comunicación SOAP
        │   ├── SiatService.php         ← servicio principal
        │   ├── SiatServiceFactory.php  ← obtiene empresa
        │   └── SiatConfig.php
        │
        ├── Cuis/
        │   └── CuisService.php
        │
        ├── Cufd/
        │   └── CufdService.php
        │
        ├── Facturacion/
        │   └── FacturaService.php
        │
        ├── Catalogos/
        │   └── SiatCatalogoService.php
        │
        └── Eventos/
            └── EventoSignificativoService.php

Así tu SiatSoapClient sí sigue siendo el corazón, pero no se encarga de saber qué empresa está trabajando. Esa responsabilidad queda arriba.

Y una corrección de seguridad importante: en producción no deberías desactivar verify_peer y verify_peer_name como en tu código actual; conviene validar correctamente el certificado TLS de SIAT.

*/