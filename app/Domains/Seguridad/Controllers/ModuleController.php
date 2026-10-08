<?php

namespace App\Domains\Seguridad\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use App\Domains\Seguridad\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
class ModuleController extends Controller
{
  /**
     * Listar módulos
     */
    public function index()
    {
        $modules = Module::orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $modules
        ]);
    }

    /**
     * Crear módulo
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:100|unique:modules,name',
            'description' => 'nullable|string|max:255',
            'status'      => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        $module = Module::create([
            'name'        => $request->name,
            'description' => $request->description,
            'status'      => $request->status
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Module created successfully.',
            'data'    => $module
        ], 201);
    }

    /**
     * Mostrar un módulo
     */
    public function show(Module $module)
    {
        return response()->json([
            'success' => true,
            'data' => $module
        ]);
    }

    /**
     * Actualizar módulo
     */
    public function update(Request $request, Module $module)
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:100|unique:modules,name,' . $module->id,
            'description' => 'nullable|string|max:255',
            'status'      => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        $module->update([
            'name'        => $request->name,
            'description' => $request->description,
            'status'      => $request->status
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Module updated successfully.',
            'data'    => $module
        ]);
    }

    /**
     * Eliminar módulo
     */
    public function destroy(Module $module)
    {
        if ($module->accesses()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'The module cannot be deleted because it has related accesses.'
            ], 409);
        }

        $module->delete();

        return response()->json([
            'success' => true,
            'message' => 'Module deleted successfully.'
        ]);
    }
}
