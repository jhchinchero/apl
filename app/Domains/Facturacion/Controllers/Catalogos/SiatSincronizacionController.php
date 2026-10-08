<?php

namespace App\Domains\Facturacion\Controllers\Catalogos;

use Throwable;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;

use App\Http\Controllers\Controller;

use App\Domains\Facturacion\Services\Siat\Sincronizacion\SiatSincronizacionService;
use App\Domains\Facturacion\Services\Siat\Catalogos\SiatCatalogoJsonService;

use App\Domains\Empresa\Models\PointOfSale;
use App\Domains\Facturacion\Models\Cuis;

class SiatSincronizacionController extends Controller
{
    private const CATALOGOS = [
        'actividades',
        'productos_servicios',
        'leyendas',
        'mensajes',
    ];

    private const PARAMETROS = [
        'eventos',
        'motivos_anulacion',
        'documentos_identidad',
        'documentos_sector',
        'tipos_emision',
        'tipos_habitacion',
        'metodos_pago',
        'monedas',
        'tipos_punto_venta',
        'tipos_factura',
        'unidades',
        'paises',
    ];

    /**
     * Devuelve el id de empresa del usuario o una respuesta de error.
     */
    private function empresa(Request $request): string|JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        if (!$user->id_company) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una empresa asignada.',
            ], 422);
        }

        return (string) $user->id_company;
    }

    public function sincronizar(
        Request $request,
        SiatSincronizacionService $siatSincronizacionService
    ): JsonResponse {

        // Fuera del try: si falla devuelve 422 automáticamente.
        $request->validate([
            'punto_venta_id' => ['integer'],
        ]);


        
        

        $idCompany = $this->empresa($request);

        if ($idCompany instanceof JsonResponse) {
            return $idCompany;
        }

        $lock = null;

        try {

            $pointOfSale = PointOfSale::with('branch')
                ->where('id', $request->punto_venta_id)
                ->where('id_company', $idCompany)
                ->first();

            if (!$pointOfSale) {
                return response()->json([
                    'success' => false,
                    'message' => 'El punto de venta no existe o no pertenece a la empresa del usuario.',
                ], 404);
            }

            if (!$pointOfSale->branch) {
                return response()->json([
                    'success' => false,
                    'message' => 'El punto de venta no tiene una sucursal asociada.',
                ], 422);
            }

            if (
                $pointOfSale->branch->codigo === null
                || $pointOfSale->codigo === null
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'La sucursal o el punto de venta no tienen código SIAT configurado.',
                ], 422);
            }

            $cuisQuery = Cuis::query()
                ->where('punto_venta_id', $pointOfSale->id)
                ->where('vigente', true)
                ->where('fecha_vigencia', '>=', now());

            if (Schema::hasColumn('cuis', 'id_company')) {
                $cuisQuery->where('id_company', $idCompany);
            }

            $cuis = $cuisQuery->latest('fecha_vigencia')->first();

            if (!$cuis) {
                return response()->json([
                    'success' => false,
                    'message' => 'El punto de venta no tiene un CUIS vigente. Debe solicitar un CUIS antes de sincronizar los catálogos.',
                    'punto_venta_id' => $pointOfSale->id,
                    'codigo_sucursal' => (int) $pointOfSale->branch->codigo,
                    'codigo_punto_venta' => (int) $pointOfSale->codigo,
                ], 422);
            }

            // Evita dos sincronizaciones simultáneas de la misma empresa.
            $lock = Cache::lock("siat-sync-{$idCompany}", 300);

            if (!$lock->get()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya hay una sincronización en curso para esta empresa.',
                ], 409);
            }

            $resultado = $siatSincronizacionService->sincronizarTodo(
                $idCompany,
                (string) $cuis->codigo,
                (int) $pointOfSale->branch->codigo,
                (int) $pointOfSale->codigo
            );

            $estado  = $resultado['estado_general'] ?? 'ERROR';
            $success = $estado === 'OK';

            return response()->json([
                'success' => $success,
                'message' => match ($estado) {
                    'OK'      => 'Catálogos y parámetros sincronizados correctamente.',
                    'PARCIAL' => 'La sincronización terminó parcialmente. Algunos servicios del SIAT presentaron errores.',
                    default   => 'La sincronización no pudo completarse.',
                },
                'empresa' => $idCompany,
                'punto_venta' => [
                    'id'     => $pointOfSale->id,
                    'codigo' => $pointOfSale->codigo,
                    'nombre' => $pointOfSale->name,
                ],
                'sucursal' => [
                    'id'     => $pointOfSale->branch->id,
                    'codigo' => $pointOfSale->branch->codigo,
                    'nombre' => $pointOfSale->branch->branch_name,
                ],
                'cuis' => [
                    'codigo'         => $cuis->codigo,
                    'fecha_vigencia' => $cuis->fecha_vigencia,
                ],
                'estado_general' => $estado,
                'resumen'        => $resultado['resumen'] ?? [],
                'catalogos'      => $resultado['catalogos'] ?? [],
                'parametros'     => $resultado['parametros'] ?? [],
            ], $estado === 'ERROR' ? 502 : 200);
        } catch (Throwable $e) {

            Log::error('Error durante la sincronización SIAT', [
                'empresa' => $idCompany,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error durante la sincronización SIAT.',
            ], 500);
        } finally {
            $lock?->release();
        }
    }

    public function catalogo(
        Request $request,
        SiatCatalogoJsonService $json,
        string $catalogo
    ): JsonResponse {

        $idCompany = $this->empresa($request);

        if ($idCompany instanceof JsonResponse) {
            return $idCompany;
        }

        // Acepta "productos-servicios" y "productos_servicios".
        $catalogo = str_replace('-', '_', $catalogo);

        if (!in_array($catalogo, self::CATALOGOS, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Catálogo no permitido.',
            ], 404);
        }

        try {

            $jsonData = $json->leerCatalogo($idCompany, $catalogo);

            if (empty($jsonData)) {
                return response()->json([
                    'success' => false,
                    'message' => 'El catálogo aún no ha sido sincronizado.',
                ], 404);
            }

            return response()->json([
                'success'  => true,
                'empresa'  => $idCompany,
                'catalogo' => $catalogo,
                'meta'     => $jsonData['meta'] ?? [],
                'data'     => $jsonData['data'] ?? [],
            ]);
        } catch (Throwable $e) {

            Log::error('Error leyendo catálogo SIAT', [
                'empresa'  => $idCompany,
                'catalogo' => $catalogo,
                'error'    => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo leer el catálogo.',
            ], 500);
        }
    }

    public function parametro(
        Request $request,
        SiatCatalogoJsonService $json,
        string $parametro
    ): JsonResponse {

        $idCompany = $this->empresa($request);

        if ($idCompany instanceof JsonResponse) {
            return $idCompany;
        }

        $parametro = str_replace('-', '_', $parametro);

        if (!in_array($parametro, self::PARAMETROS, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Parámetro no permitido.',
            ], 404);
        }

        try {

            $jsonData = $json->leerParametro($idCompany, $parametro);

            if (empty($jsonData)) {
                return response()->json([
                    'success' => false,
                    'message' => 'El parámetro aún no ha sido sincronizado.',
                ], 404);
            }

            return response()->json([
                'success'   => true,
                'empresa'   => $idCompany,
                'parametro' => $parametro,
                'meta'      => $jsonData['meta'] ?? [],
                'data'      => $jsonData['data'] ?? [],
            ]);
        } catch (Throwable $e) {

            Log::error('Error leyendo parámetro SIAT', [
                'empresa'   => $idCompany,
                'parametro' => $parametro,
                'error'     => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo leer el parámetro.',
            ], 500);
        }
    }

    public function estado(
        Request $request,
        SiatCatalogoJsonService $json
    ): JsonResponse {

        $idCompany = $this->empresa($request);

        if ($idCompany instanceof JsonResponse) {
            return $idCompany;
        }

        try {

            return response()->json([
                'success' => true,
                'empresa' => $idCompany,
                'data'    => $json->estado($idCompany),
            ]);
        } catch (Throwable $e) {

            Log::error('Error leyendo estado SIAT', [
                'empresa' => $idCompany,
                'error'   => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo leer el estado de sincronización.',
            ], 500);
        }
    }
}



/*
Acción	Método	URL
Sincronizar	
POST

{{base_url}}/api/siat/catalogos/sincronizar

Estado	GET	
{{base_url}}/api/siat/catalogos/estado

Un catálogo	GET	

{{base_url}}/api/siat/catalogos/catalogo/productos-servicios

Otro catálogo	

GET	{{base_url}}/api/siat/catalogos/catalogo/actividades

Un parámetro	

GET	{{base_url}}/api/siat/catalogos/parametro/monedas

Otro parámetro	

GET	{{base_url}}/api/siat/catalogos/parametro/metodos-pago


{
  "punto_venta_id": 1
}

Catálogos: actividades, productos_servicios, leyendas, mensajes
Parámetros: eventos, motivos_anulacion, documentos_identidad, 
documentos_sector, tipos_emision, tipos_habitacion, 
metodos_pago, monedas, tipos_punto_venta, tipos_factura, 
unidades, paises

*/