<?php



namespace App\Domains\Facturacion\Services\Siat\Core;

use App\Domains\Facturacion\Services\Siat\Core\SiatSoapClient;


use App\Domains\Facturacion\Models\SiatConfiguracion;
use RuntimeException;

class SiatService
{
    private SiatConfiguracion $configuracion;

    public function __construct(
        SiatConfiguracion $configuracion
    ) {
        $this->configuracion = $configuracion;
    }

    /**
     * Obtiene el cliente SOAP utilizando
     * la configuración de la <empresa class="1012">11012</empresa>
     */
    public function soap(string $wsdl): SiatSoapClient
    {
        
        if (!$this->configuracion->activo) {
            throw new RuntimeException(
                'La configuración SIAT de la empresa esta inactiva.'
            );
        }
   // dd($this->configuracion->token_delegado);

       

        return new SiatSoapClient(
            $wsdl,
           $this->configuracion->token_delegado
        );
    }

    /**
     * Configuración SIAT de la empresa.
     */
    public function config(): SiatConfiguracion
    {
        return $this->configuracion;
    }
    
}
