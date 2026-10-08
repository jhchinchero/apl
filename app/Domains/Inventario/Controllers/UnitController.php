<?php

namespace App\Domains\Inventario\Controllers;

use App\Http\Controllers\Controller;

use App\Domains\Inventario\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;



class UnitController extends Controller
{
    public function index()
    {
        try {
            $units = Unit::orderBy('unit_name')->get();

            return response()->json([
                'success' => true,
                'data' => $units
            ]);

        } catch (\Throwable $e) {
            Log::error('Error listando unidades de medida', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudieron obtener las unidades de medida.'
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'unit_name' => ['required', 'string', 'max:100'],
                'symbol'    => ['nullable', 'string', 'max:20'],
            ]);

            // Obtener el último código generado
            $lastUnit = Unit::orderByDesc('id')->first();

            if (!$lastUnit) {
                $nextNumber = 1;
            } else {
                $lastNumber = (int) str_replace('UNI-', '', $lastUnit->code_unit);
                $nextNumber = $lastNumber + 1;
            }

            // Generar código automáticamente
            do {
                $codeUnit = 'UNI-' . str_pad(
                    $nextNumber,
                    5,
                    '0',
                    STR_PAD_LEFT
                );

                $exists = Unit::where('code_unit', $codeUnit)->exists();

                $nextNumber++;

            } while ($exists);

            $unit = Unit::create([
                'code_unit' => $codeUnit,
                'unit_name' => trim($validated['unit_name']),
                'symbol'    => !empty($validated['symbol'])
                    ? trim($validated['symbol'])
                    : null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Unidad de medida registrada correctamente.',
                'data'    => $unit
            ], 201);

        } catch (\Throwable $e) {
            Log::error('Error creando unidad de medida', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo registrar la unidad de medida.'
            ], 500);
        }
    }

    public function show(int $id)
    {
        try {
            $unit = Unit::find($id);

            if (!$unit) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unidad de medida no encontrada.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $unit
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo obtener la unidad de medida.'
            ], 500);
        }
    }

    public function update(Request $request, int $id)
    {
        try {
            $unit = Unit::find($id);

            if (!$unit) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unidad de medida no encontrada.'
                ], 404);
            }

            $validated = $request->validate([
                'unit_name' => ['required', 'string', 'max:100'],
                'symbol'    => ['nullable', 'string', 'max:20'],
            ]);

            // Solo modificamos unidad y símbolo
            $unit->update([
                'unit_name' => trim($validated['unit_name']),
                'symbol'    => !empty($validated['symbol'])
                    ? trim($validated['symbol'])
                    : null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Unidad de medida actualizada correctamente.',
                'data'    => $unit->fresh()
            ]);

        } catch (\Throwable $e) {
            Log::error('Error actualizando unidad de medida', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo actualizar la unidad de medida.'
            ], 500);
        }
    }

    public function destroy(int $id)
    {
        try {
            $unit = Unit::find($id);

            if (!$unit) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unidad de medida no encontrada.'
                ], 404);
            }

            $unit->delete();

            return response()->json([
                'success' => true,
                'message' => 'Unidad de medida eliminada correctamente.'
            ]);

        } catch (\Throwable $e) {
            Log::error('Error eliminando unidad de medida', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo eliminar la unidad de medida.'
            ], 500);
        }
    }
}