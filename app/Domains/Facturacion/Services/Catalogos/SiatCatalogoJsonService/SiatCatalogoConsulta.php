<?php

use App\Domains\Facturacion\Services\Siat\Catalogos\SiatCatalogoJsonService;

class SiatCatalogoConsulta
{
    public function __construct(private SiatCatalogoJsonService $json) {}

    public function existeUnidad(string $idCompany, int $codigo): bool
    {
        return collect($this->json->datosParametro($idCompany, 'unidades'))
            ->contains('codigoClasificador', $codigo);
    }

    public function productoValido(string $idCompany, int $actividad, int $codigoProducto): bool
    {
        return collect($this->json->datosCatalogo($idCompany, 'productos_servicios'))
            ->contains(fn ($p) =>
                (int) $p['codigoActividad'] === $actividad
                && (int) $p['codigoProducto'] === $codigoProducto
            );
    }

    public function leyendaAleatoria(string $idCompany, int $actividad): ?string
    {
        $leyendas = collect($this->json->datosCatalogo($idCompany, 'leyendas'))
            ->where('codigoActividad', $actividad);

        return $leyendas->isEmpty()
            ? null
            : $leyendas->random()['descripcionLeyenda'];
    }
}