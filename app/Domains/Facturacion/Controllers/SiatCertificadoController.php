<?php

namespace App\Domains\Facturacion\Controllers;



use App\Http\Controllers\Controller;
use App\Domains\Facturacion\Models\SiatCertificado;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;

class SiatCertificadoController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $certificados = SiatCertificado::with('company')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $certificados,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */

    public function show($id)
    {
        $certificado = SiatCertificado::with('company')
            ->find($id);

        if (!$certificado) {
            return response()->json([
                'success' => false,
                'message' => 'Certificado SIAT no encontrado.',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $certificado,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | OBTENER POR EMPRESA
    |--------------------------------------------------------------------------
    */

    public function getByCompany($idCompany)
    {
        $certificados = SiatCertificado::with('company')
            ->where('id_company', $idCompany)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'exists' => $certificados->isNotEmpty(),
            'data' => $certificados,
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
            'id_company' => ['required', 'string', 'exists:companies,id_company'],
            'nombre' => ['required', 'string', 'max:150'],
            'certificado_file' => ['required', 'file', 'max:5120'],
            'password' => ['required', 'string', 'min:6'],
            'serial' => ['nullable', 'string', 'max:255'],
            'fecha_vencimiento' => ['nullable', 'date'],
            'activo' => ['nullable', 'boolean'],
        ]);

        /*
        |--------------------------------------------------------------------------
        | VALIDAR EXTENSIÓN DEL ARCHIVO
        |--------------------------------------------------------------------------
        */

        $file = $request->file('certificado_file');
        $extension = strtolower($file->getClientOriginalExtension());

        if (!in_array($extension, ['pfx', 'p12'])) {
            return response()->json([
                'success' => false,
                'message' => 'El archivo debe ser .pfx o .p12',
                'errors' => ['certificado_file' => ['Solo se aceptan archivos .pfx o .p12']],
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | VERIFICAR CERTIFICADO EXISTENTE
        |--------------------------------------------------------------------------
        */

        $existe = SiatCertificado::where(
            'id_company',
            $validated['id_company']
        )->first();

        if ($existe) {
            return response()->json([
                'success' => false,
                'message' => 'La empresa ya tiene un certificado SIAT registrado.',
                'data' => $existe,
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | PROCESAR ARCHIVO
        |--------------------------------------------------------------------------
        */

        $idCompany = $validated['id_company'];
        $filename = time() . '_' . uniqid() . '.' . $extension;
        $path = $file->storeAs("siat-certificados/{$idCompany}", $filename, 'local');

        if (!$path) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo guardar el archivo.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | CREAR CERTIFICADO
        |--------------------------------------------------------------------------
        */

        $certificado = SiatCertificado::create([
            'id_company' => $validated['id_company'],
            'nombre' => $validated['nombre'],
            'certificado_path' => $path,
            'llave_privada_path' => null,
            'password' => $validated['password'],
            'serial' => $validated['serial'] ?? null,
            'fecha_inicio' => now(),
            'fecha_vencimiento' => $validated['fecha_vencimiento'] ?? null,
            'activo' => $validated['activo'] ?? true,
        ]);

        $certificado->load('company');

        return response()->json([
            'success' => true,
            'message' => 'Certificado SIAT registrado correctamente.',
            'data' => $certificado,
        ], 201);

    } catch (ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Los datos enviados no son válidos.',
            'errors' => $e->errors(),
        ], 422);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error al procesar el certificado: ' . $e->getMessage(),
        ], 500);
    }
}
    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

   public function update(Request $request, $id)
{
    try {
        $certificado = SiatCertificado::find($id);

        if (!$certificado) {
            return response()->json([
                'success' => false,
                'message' => 'Certificado SIAT no encontrado.',
                'data' => null,
            ], 404);
        }

        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'certificado_file' => ['nullable', 'file', 'max:5120'],  // ✅ Sin mimes
            'password' => ['required', 'string', 'min:6'],
            'serial' => ['nullable', 'string'],
            'fecha_vencimiento' => ['nullable', 'date'],
            'activo' => ['nullable', 'boolean'],
        ]);

        /*
        |--------------------------------------------------------------------------
        | VALIDAR EXTENSIÓN SI HAY ARCHIVO NUEVO
        |--------------------------------------------------------------------------
        */

        if ($request->hasFile('certificado_file')) {
            $file = $request->file('certificado_file');
            $extension = strtolower($file->getClientOriginalExtension());

            if (!in_array($extension, ['pfx', 'p12'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'El archivo debe ser .pfx o .p12',
                    'errors' => ['certificado_file' => ['Formato no válido']],
                ], 422);
            }

            // Eliminar archivo anterior
            if ($certificado->certificado_path && Storage::disk('private')->exists($certificado->certificado_path)) {
                Storage::disk('private')->delete($certificado->certificado_path);
            }

            // Guardar nuevo archivo
            $filename = time() . '_' . uniqid() . '.' . $extension;
            $path = $file->storeAs('siat-certificados', $filename, 'private');

            $validated['certificado_path'] = $path;
        }

        /*
        |--------------------------------------------------------------------------
        | ACTUALIZAR
        |--------------------------------------------------------------------------
        */

        $certificado->update($validated);
        $certificado->load('company');

        return response()->json([
            'success' => true,
            'message' => 'Certificado SIAT actualizado correctamente.',
            'data' => $certificado,
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
        $certificado = SiatCertificado::find($id);

        if (!$certificado) {
            return response()->json([
                'success' => false,
                'message' => 'Certificado SIAT no encontrado.',
            ], 404);
        }

        // Eliminar archivo
        if ($certificado->certificado_path && Storage::disk('private')->exists($certificado->certificado_path)) {
            Storage::disk('private')->delete($certificado->certificado_path);
        }

        $certificado->delete();

        return response()->json([
            'success' => true,
            'message' => 'Certificado SIAT eliminado correctamente.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | TOGGLE ACTIVE
    |--------------------------------------------------------------------------
    */

    public function toggleActive($id)
    {
        $certificado = SiatCertificado::find($id);

        if (!$certificado) {
            return response()->json([
                'success' => false,
                'message' => 'Certificado SIAT no encontrado.',
            ], 404);
        }

        $certificado->activo = !$certificado->activo;
        $certificado->save();

        return response()->json([
            'success' => true,
            'message' => $certificado->activo
                ? 'Certificado SIAT activado.'
                : 'Certificado SIAT desactivado.',
            'data' => $certificado,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDAR CERTIFICADO
    |--------------------------------------------------------------------------
    */

    public function validateCertificate($id)
    {
        $certificado = SiatCertificado::find($id);

        if (!$certificado) {
            return response()->json([
                'success' => false,
                'message' => 'Certificado SIAT no encontrado.',
            ], 404);
        }

        try {

            $rutaCertificado = Storage::disk('private')
                ->path($certificado->certificado_path);

            // Validar que el archivo existe
            if (!file_exists($rutaCertificado)) {
                return response()->json([
                    'success' => false,
                    'message' => 'El archivo del certificado no se encontró en el servidor.',
                ], 422);
            }

            // Obtener información del certificado (básico)
            $openssl = openssl_pkcs12_read(
                file_get_contents($rutaCertificado),
                $certs,
                $certificado->password
            );

            if (!$openssl) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo leer el certificado. Verifique la contraseña.',
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'Certificado válido.',
                'data' => [
                    'valido' => true,
                    'serial' => $certificado->serial,
                    'vencimiento' => $certificado->fecha_vencimiento,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al validar el certificado: ' . $e->getMessage(),
            ], 422);
        }
    }
}