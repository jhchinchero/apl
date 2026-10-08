<?php

namespace App\Domains\Facturacion\Services\Siat\Catalogos;
use Illuminate\Support\Facades\Storage;
use RuntimeException;




class SiatCatalogoJsonService
{
    private string $basePath = 'siat';

    private string $disk = 'local';

    /*
    |--------------------------------------------------------------------------
    | Directorio raíz de empresa
    |--------------------------------------------------------------------------
    */

    private function companyPath(
        string $idCompany
    ): string {
        return $this->basePath . '/' . $idCompany;
    }

    /*
    |--------------------------------------------------------------------------
    | CATÁLOGOS
    |--------------------------------------------------------------------------
    */

    private function catalogoPath(
        string $idCompany,
        string $catalogo
    ): string {
        return $this->companyPath($idCompany)
            . '/catalogos/'
            . $catalogo
            . '.json';
    }

    /*
    |--------------------------------------------------------------------------
    | PARÁMETROS
    |--------------------------------------------------------------------------
    */

    private function parametroPath(
        string $idCompany,
        string $parametro
    ): string {
        return $this->companyPath($idCompany)
            . '/parametros/'
            . $parametro
            . '.json';
    }

    /*
    |--------------------------------------------------------------------------
    | ESTADO
    |--------------------------------------------------------------------------
    */

    private function estadoPath(
        string $idCompany
    ): string {
        return $this->companyPath($idCompany)
            . '/sincronizacion/estado.json';
    }

    /*
    |--------------------------------------------------------------------------
    | HISTORIAL
    |--------------------------------------------------------------------------
    */

    private function historialPath(
        string $idCompany,
        string $catalogo,
        string $fecha
    ): string {
        return $this->companyPath($idCompany)
            . '/sincronizacion/historial/'
            . $catalogo
            . '/'
            . $fecha
            . '.json';
    }

    /*
    |--------------------------------------------------------------------------
    | GUARDAR CATÁLOGO
    |--------------------------------------------------------------------------
    */

    public function guardarCatalogo(
        string $idCompany,
        string $catalogo,
        array $data,
        array $meta = []
    ): array {

        return $this->guardar(
            $this->catalogoPath(
                $idCompany,
                $catalogo
            ),
            $idCompany,
            $catalogo,
            'catalogo',
            $data,
            $meta
        );
    }

    /*
    |--------------------------------------------------------------------------
    | GUARDAR PARÁMETRO
    |--------------------------------------------------------------------------
    */

    public function guardarParametro(
        string $idCompany,
        string $parametro,
        array $data,
        array $meta = []
    ): array {

        return $this->guardar(
            $this->parametroPath(
                $idCompany,
                $parametro
            ),
            $idCompany,
            $parametro,
            'parametro',
            $data,
            $meta
        );
    }

    /*
    |--------------------------------------------------------------------------
    | GUARDAR
    |--------------------------------------------------------------------------
    */

    private function guardar(
        string $path,
        string $idCompany,
        string $nombre,
        string $tipo,
        array $data,
        array $meta = []
    ): array {

        $disk = Storage::disk($this->disk);

        $hash = hash(
            'sha256',
            json_encode(
                $data,
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            )
        );

        $anterior = null;

        if ($disk->exists($path)) {

            $contenidoAnterior =
                $disk->get($path);

            $anterior =
                json_decode(
                    $contenidoAnterior,
                    true
                );
        }

        $hashAnterior =
            $anterior['meta']['hash']
            ?? null;

        $cambio =
            $hashAnterior !== null
            && $hashAnterior !== $hash;

        $fecha = now();

        $payload = [

            'meta' => array_merge(

                [

                    'empresa' =>
                        $idCompany,

                    'nombre' =>
                        $nombre,

                    'tipo' =>
                        $tipo,

                    'fecha_sincronizacion' =>
                        $fecha->toDateTimeString(),

                    'cantidad' =>
                        count($data),

                    'hash' =>
                        $hash,

                    'hash_anterior' =>
                        $hashAnterior,

                    'cambio_detectado' =>
                        $cambio,

                    'version' =>
                        1,

                ],

                $meta
            ),

            'data' =>
                array_values($data),
        ];

        $json = json_encode(
            $payload,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );

        if ($json === false) {

            throw new RuntimeException(
                "No se pudo generar {$nombre}.json"
            );
        }

        $disk->makeDirectory(
            dirname($path)
        );

        /*
        |--------------------------------------------------------------------------
        | Archivo temporal
        |--------------------------------------------------------------------------
        */

        $tmpPath =
            $path . '.tmp';

        $disk->put(
            $tmpPath,
            $json
        );

        /*
        |--------------------------------------------------------------------------
        | Reemplazo seguro
        |--------------------------------------------------------------------------
        */

        if ($disk->exists($path)) {

            $disk->delete($path);
        }

        $disk->move(
            $tmpPath,
            $path
        );

        /*
        |--------------------------------------------------------------------------
        | Historial
        |--------------------------------------------------------------------------
        */

        $historial = [

            'empresa' =>
                $idCompany,

            'nombre' =>
                $nombre,

            'tipo' =>
                $tipo,

            'fecha' =>
                $fecha->toDateTimeString(),

            'cantidad_anterior' =>
                $anterior['meta']['cantidad']
                ?? null,

            'cantidad_nueva' =>
                count($data),

            'hash_anterior' =>
                $hashAnterior,

            'hash_nuevo' =>
                $hash,

            'cambio_detectado' =>
                $cambio,

            'meta' =>
                $meta,
        ];

        $historialPath =
            $this->historialPath(
                $idCompany,
                $nombre,
                $fecha->format(
                    'Y-m-d_H-i-s'
                )
            );

        $disk->makeDirectory(
            dirname($historialPath)
        );

        $disk->put(
            $historialPath,
            json_encode(
                $historial,
                JSON_PRETTY_PRINT |
                JSON_UNESCAPED_UNICODE
            )
        );

        return [

            'hash' =>
                $hash,

            'hash_anterior' =>
                $hashAnterior,

            'cantidad' =>
                count($data),

            'cambio_detectado' =>
                $cambio,

        ];
    }

    /*
    |--------------------------------------------------------------------------
    | LEER CATÁLOGO
    |--------------------------------------------------------------------------
    */

    public function leerCatalogo(
        string $idCompany,
        string $catalogo
    ): array {

        return $this->leerArchivo(
            $this->catalogoPath(
                $idCompany,
                $catalogo
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | LEER PARÁMETRO
    |--------------------------------------------------------------------------
    */

    public function leerParametro(
        string $idCompany,
        string $parametro
    ): array {

        return $this->leerArchivo(
            $this->parametroPath(
                $idCompany,
                $parametro
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | LEER ARCHIVO
    |--------------------------------------------------------------------------
    */

    private function leerArchivo(
        string $path
    ): array {

        $disk = Storage::disk(
            $this->disk
        );

        if (!$disk->exists($path)) {

            return [];
        }

        $contenido =
            $disk->get($path);

        return json_decode(
            $contenido,
            true
        ) ?? [];
    }

    /*
    |--------------------------------------------------------------------------
    | DATOS DE CATÁLOGO
    |--------------------------------------------------------------------------
    */

    public function datosCatalogo(
        string $idCompany,
        string $catalogo
    ): array {

        $json =
            $this->leerCatalogo(
                $idCompany,
                $catalogo
            );

        return $json['data'] ?? [];
    }

    /*
    |--------------------------------------------------------------------------
    | DATOS DE PARÁMETRO
    |--------------------------------------------------------------------------
    */

    public function datosParametro(
        string $idCompany,
        string $parametro
    ): array {

        $json =
            $this->leerParametro(
                $idCompany,
                $parametro
            );

        return $json['data'] ?? [];
    }

    /*
    |--------------------------------------------------------------------------
    | META
    |--------------------------------------------------------------------------
    */

    public function metaCatalogo(
        string $idCompany,
        string $catalogo
    ): array {

        $json =
            $this->leerCatalogo(
                $idCompany,
                $catalogo
            );

        return $json['meta'] ?? [];
    }

    /*
    |--------------------------------------------------------------------------
    | ESTADO
    |--------------------------------------------------------------------------
    */

    public function guardarEstado(
        string $idCompany,
        array $estado
    ): void {

        $disk = Storage::disk(
            $this->disk
        );

        $path =
            $this->estadoPath(
                $idCompany
            );

        $disk->makeDirectory(
            dirname($path)
        );

        $payload = [

            'empresa' =>
                $idCompany,

            'ultima_sincronizacion' =>
                now()->toDateTimeString(),

            'estado_general' =>
                $estado['estado_general']
                ?? 'OK',

            'resumen' =>
                $estado['resumen']
                ?? [],

            'catalogos' =>
                $estado['catalogos']
                ?? [],

            'parametros' =>
                $estado['parametros']
                ?? [],
        ];

        $disk->put(
            $path,
            json_encode(
                $payload,
                JSON_PRETTY_PRINT |
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | LEER ESTADO
    |--------------------------------------------------------------------------
    */

    public function estado(
        string $idCompany
    ): array {

        $disk = Storage::disk(
            $this->disk
        );

        $path =
            $this->estadoPath(
                $idCompany
            );

        if (!$disk->exists($path)) {

            return [

                'empresa' =>
                    $idCompany,

                'estado_general' =>
                    'NUNCA_SINCRONIZADO',

            ];
        }

        return json_decode(
            $disk->get($path),
            true
        ) ?? [];
    }
}