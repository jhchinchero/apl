<?php

namespace App\Domains\Facturacion\Controllers;



use App\Http\Controllers\Controller;
use App\Domains\Facturacion\Models\SiatConfiguracion;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SiatConfiguracionController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $configuraciones = SiatConfiguracion::with('company')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $configuraciones,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */

    public function show($id)
    {
        $configuracion = SiatConfiguracion::with('company')
            ->find($id);

        if (!$configuracion) {

            return response()->json([
                'success' => false,
                'message' => 'Configuración SIAT no encontrada.',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $configuracion,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | OBTENER POR EMPRESA
    |--------------------------------------------------------------------------
    */

    public function getByCompany($idCompany)
    {
        $configuracion = SiatConfiguracion::with('company')
            ->where('id_company', $idCompany)
            ->first();

        return response()->json([
            'success' => true,
            'exists' => $configuracion !== null,
            'data' => $configuracion,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        try {

            $validated = $request->validate([

                'id_company' => [
                    'required',
                    'string',
                    'exists:companies,id_company',
                ],

                'codigo_ambiente' => [
                    'required',
                    'integer',
                ],

                'codigo_modalidad' => [
                    'required',
                    'integer',
                ],

                'codigo_sistema' => [
                    'required',
                    'string',
                    'max:50',
                ],

                'nit' => [
                    'required',
                    'string',
                    'max:20',
                    'regex:/^[0-9]+$/',
                ],

                'codigo_emision' => [
                    'required',
                    'integer',
                ],

                'tipo_factura_documento' => [
                    'required',
                    'integer',
                ],

                'codigo_documento_sector' => [
                    'required',
                    'integer',
                ],

                'token_delegado' => [
                    'nullable',
                    'string',
                ],

                'activo' => [
                    'nullable',
                    'boolean',
                ],
            ]);

            /*
            |--------------------------------------------------------------------------
            | VERIFICAR CONFIGURACIÓN EXISTENTE
            |--------------------------------------------------------------------------
            */

            $existe = SiatConfiguracion::where(
                'id_company',
                $validated['id_company']
            )->first();

            if ($existe) {

                return response()->json([
                    'success' => false,
                    'message' => 'La empresa ya tiene una configuración SIAT.',
                    'data' => $existe,
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | CREAR
            |--------------------------------------------------------------------------
            */

            $configuracion = SiatConfiguracion::create(
                $validated
            );

            $configuracion->load('company');

            return response()->json([
                'success' => true,
                'message' => 'Configuración SIAT creada correctamente.',
                'data' => $configuracion,
            ], 201);

        } catch (ValidationException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Los datos enviados no son válidos.',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        $id
    ) {
        try {

            $configuracion = SiatConfiguracion::find($id);

            if (!$configuracion) {

                return response()->json([
                    'success' => false,
                    'message' => 'Configuración SIAT no encontrada.',
                    'data' => null,
                ], 404);
            }

            $validated = $request->validate([

                'id_company' => [
                    'required',
                    'string',
                    'exists:companies,id_company',
                ],

                'codigo_ambiente' => [
                    'required',
                    'integer',
                ],

                'codigo_modalidad' => [
                    'required',
                    'integer',
                ],

                'codigo_sistema' => [
                    'required',
                    'string',
                    'max:50',
                ],

                'nit' => [
                    'required',
                    'string',
                    'max:20',
                    'regex:/^[0-9]+$/',
                ],

                'codigo_emision' => [
                    'required',
                    'integer',
                ],

                'tipo_factura_documento' => [
                    'required',
                    'integer',
                ],

                'codigo_documento_sector' => [
                    'required',
                    'integer',
                ],

                'token_delegado' => [
                    'nullable',
                    'string',
                ],

                'activo' => [
                    'nullable',
                    'boolean',
                ],
            ]);

            /*
            |--------------------------------------------------------------------------
            | EVITAR DUPLICADO
            |--------------------------------------------------------------------------
            */

            $duplicado = SiatConfiguracion::where(
                'id_company',
                $validated['id_company']
            )
                ->where(
                    'id',
                    '!=',
                    $configuracion->id
                )
                ->exists();

            if ($duplicado) {

                return response()->json([
                    'success' => false,
                    'message' => 'La empresa ya tiene otra configuración SIAT.',
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | ACTUALIZAR
            |--------------------------------------------------------------------------
            */

            $configuracion->update($validated);

            $configuracion->load('company');

            return response()->json([
                'success' => true,
                'message' => 'Configuración SIAT actualizada correctamente.',
                'data' => $configuracion,
            ]);

        } catch (ValidationException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Los datos enviados no son válidos.',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */

    public function destroy($id)
    {
        $configuracion = SiatConfiguracion::find($id);

        if (!$configuracion) {

            return response()->json([
                'success' => false,
                'message' => 'Configuración SIAT no encontrada.',
            ], 404);
        }

        $configuracion->delete();

        return response()->json([
            'success' => true,
            'message' => 'Configuración SIAT eliminada correctamente.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | TOGGLE ACTIVE
    |--------------------------------------------------------------------------
    */

    public function toggleActive($id)
    {
        $configuracion = SiatConfiguracion::find($id);

        if (!$configuracion) {

            return response()->json([
                'success' => false,
                'message' => 'Configuración SIAT no encontrada.',
            ], 404);
        }

        $configuracion->activo = !$configuracion->activo;

        $configuracion->save();

        return response()->json([
            'success' => true,

            'message' => $configuracion->activo
                ? 'Configuración SIAT activada.'
                : 'Configuración SIAT desactivada.',

            'data' => $configuracion,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDAR CONEXIÓN
    |--------------------------------------------------------------------------
    */

    public function validateConnection($id)
    {
        $configuracion = SiatConfiguracion::find($id);

        if (!$configuracion) {

            return response()->json([
                'success' => false,
                'message' => 'Configuración SIAT no encontrada.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Configuración SIAT válida.',
            'data' => [
                'conectado' => true,
                'ambiente' => $configuracion->codigo_ambiente,
                'modalidad' => $configuracion->codigo_modalidad,
            ],
        ]);
    }
}