<?php



namespace App\Domains\Facturacion\Services\Siat\Core;

use SoapClient;


class SiatSoapClient
{
    private string $wsdl;
    private ?string $apiKey;

    public function __construct(
        string $wsdl,
        ?string $apiKey = null
    ) {
        $this->wsdl = $wsdl;
        $this->apiKey = $apiKey;
    }

    /**
     * Ejecuta una operación SOAP contra SIAT.
     */
    public function call(string $method, array $params): mixed
    {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],

            'http' => [
                'header' =>  $this->buildHeaders(),
            ],
        ]);

        $client = new SoapClient($this->wsdl, [
            'stream_context' => $context,
            'cache_wsdl' => WSDL_CACHE_NONE,
            'trace' => true,
            'exceptions' => true,
            'connection_timeout' => 30,
            'soap_version' => SOAP_1_1,
        ]);

        return $client->__soapCall(
            $method,
            [$params]
        );
    }

    /**
     * Headers HTTP enviados a SIAT.
     */
      private function buildHeaders(): string
    {
        if (!$this->apiKey) {
            return '';
        }

        return
            "apikey: TokenApi {$this->apiKey}\r\n";
    }
}
