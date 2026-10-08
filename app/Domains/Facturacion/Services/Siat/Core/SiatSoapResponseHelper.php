<?php
namespace App\Domains\Facturacion\Services\Siat\Core;
class SiatSoapResponseHelper
{
    public static function toArray(
        mixed $data
    ): array {

        if (is_array($data)) {
            return $data;
        }

        if (is_object($data)) {

            return json_decode(
                json_encode($data),
                true
            ) ?? [];
        }

        return [];
    }

    public static function respuesta(
        mixed $response
    ): array {

        $array =
            self::toArray($response);

        foreach (
            $array as $key => $value
        ) {

            if (
                str_starts_with(
                    strtolower($key),
                    'respuesta'
                )
            ) {

                return self::toArray(
                    $value
                );
            }
        }

        return $array;
    }

    public static function transaccion(
        mixed $response
    ): bool {

        $respuesta =
            self::respuesta(
                $response
            );

        return
            isset(
                $respuesta['transaccion']
            )
            &&
            (
                (bool)
                $respuesta['transaccion']
            );
    }

    public static function mensaje(
        mixed $response
    ): ?string {

        $respuesta =
            self::respuesta(
                $response
            );

        $mensajes =
            $respuesta['mensajesList']
            ?? null;

        if (!$mensajes) {
            return null;
        }

        if (
            isset(
                $mensajes['descripcion']
            )
        ) {

            return
                $mensajes['descripcion'];
        }

        if (
            isset(
                $mensajes[0]['descripcion']
            )
        ) {

            return
                $mensajes[0]['descripcion'];
        }

        return null;
    }

    public static function extractList(
        mixed $response
    ): array {

        $respuesta =
            self::respuesta(
                $response
            );

        foreach (
            $respuesta as $key => $value
        ) {

            if (
                in_array(
                    strtolower($key),
                    [
                        'transaccion',
                        'mensajeslist',
                    ],
                    true
                )
            ) {
                continue;
            }

            if (
                is_array($value)
            ) {

                if (
                    array_is_list($value)
                ) {

                    return $value;
                }

                foreach (
                    $value as $subValue
                ) {

                    if (
                        is_array($subValue)
                        &&
                        array_is_list(
                            $subValue
                        )
                    ) {

                        return $subValue;
                    }
                }
            }
        }

        return [];
    }
}