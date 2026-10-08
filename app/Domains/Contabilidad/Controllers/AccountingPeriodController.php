<?php

namespace App\Domains\Contabilidad\Controllers;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

use App\Domains\Contabilidad\Models\AccountingPeriod;
use App\Domains\Empresa\Models\Branch;

class AccountingPeriodController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | LISTAR PERÍODOS
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        try {

            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado.'
                ], 401);
            }

            $idCompany = $user->id_company;
$query = AccountingPeriod::with([
    'branch:id,branch_name',
    'closedBy:id,name'
])
->where('id_company', $idCompany);

            /*
            |--------------------------------------------------------------------------
            | FILTRO SUCURSAL
            |--------------------------------------------------------------------------
            */

            if ($request->filled('branch_id')) {
                $query->where(
                    'branch_id',
                    $request->branch_id
                );
            }

            /*
            |--------------------------------------------------------------------------
            | FILTRO ESTADO
            |--------------------------------------------------------------------------
            */

            if ($request->filled('status')) {
                $query->where(
                    'status',
                    strtoupper($request->status)
                );
            }

            /*
            |--------------------------------------------------------------------------
            | FILTRO PERÍODO
            |--------------------------------------------------------------------------
            */

            if ($request->filled('period')) {
                $query->where(
                    'period',
                    $request->period
                );
            }

            $periods = $query
                ->orderByDesc('period')
                ->orderBy('branch_id')
                ->paginate(
                    $request->get('per_page', 10)
                );

            return response()->json([
                'success' => true,
                'message' => 'Períodos obtenidos correctamente.',
                'data' => $periods
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener períodos.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | OBTENER PERÍODO ACTUAL
    |--------------------------------------------------------------------------
    */

    public function current(Request $request)
    {
        try {

            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado.'
                ], 401);
            }

            $idCompany = $user->id_company;

            $branchId = $request->branch_id;

            $today = Carbon::today();

            $query = AccountingPeriod::where(
                'id_company',
                $idCompany
            )
            ->where('status', 'OPEN')
            ->whereDate(
                'start_date',
                '<=',
                $today
            )
            ->whereDate(
                'end_date',
                '>=',
                $today
            );

            if ($branchId) {

                $query->where(function ($q) use ($branchId) {

                    $q->where(
                        'branch_id',
                        $branchId
                    )
                    ->orWhereNull('branch_id');

                });
            }

            $period = $query
                ->orderByRaw(
                    'CASE WHEN branch_id IS NULL THEN 1 ELSE 0 END'
                )
                ->first();

            if (!$period) {

                return response()->json([
                    'success' => false,
                    'message' => 'No existe un período contable abierto para la fecha actual.',
                    'data' => null
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Período actual obtenido correctamente.',
                'data' => $period
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener período actual.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | REGISTRAR / ABRIR PERÍODO
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        try {

            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado.'
                ], 401);
            }

            $idCompany = $user->id_company;

            /*
            |--------------------------------------------------------------------------
            | VALIDACIÓN
            |--------------------------------------------------------------------------
            */

            $validator = Validator::make(
                $request->all(),
                [
                    'branch_id' => [
                        'nullable',
                        'integer',
                        'exists:branches,id'
                    ],

                    'type' => [
                        'required',
                        'string',
                        'max:20'
                    ],

                    'period' => [
                        'required',
                        'date_format:Y-m'
                    ],
                ],
                [
                    'period.date_format' =>
                        'El período debe tener el formato YYYY-MM.',

                    'branch_id.exists' =>
                        'La sucursal seleccionada no existe.'
                ]
            );

            if ($validator->fails()) {

                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación.',
                    'errors' => $validator->errors()
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | DATOS
            |--------------------------------------------------------------------------
            */

            $period = $request->period;

            $startDate = Carbon::createFromFormat(
                'Y-m',
                $period
            )->startOfMonth();

            $endDate = Carbon::createFromFormat(
                'Y-m',
                $period
            )->endOfMonth();

            /*
            |--------------------------------------------------------------------------
            | VALIDAR QUE LA SUCURSAL PERTENEZCA A LA EMPRESA
            |--------------------------------------------------------------------------
            */

            if ($request->branch_id) {

                $branchExists = Branch::where(
                    'id',
                    $request->branch_id
                )
                ->where(
                    'id_company',
                    $idCompany
                )
                ->exists();

                if (!$branchExists) {

                    return response()->json([
                        'success' => false,
                        'message' =>
                            'La sucursal no pertenece a la empresa.'
                    ], 422);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | VALIDAR DUPLICADO
            |--------------------------------------------------------------------------
            */

            $exists = AccountingPeriod::where(
                'id_company',
                $idCompany
            )
            ->where(
                'branch_id',
                $request->branch_id
            )
            ->where(
                'period',
                $period
            )
            ->exists();

            if ($exists) {

                return response()->json([
                    'success' => false,
                    'message' =>
                        'Ya existe un período para esta sucursal y mes.'
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | VALIDAR QUE NO EXISTA OTRO PERÍODO ABIERTO
            |--------------------------------------------------------------------------
            */

            $openPeriod = AccountingPeriod::where(
                'id_company',
                $idCompany
            )
            ->where(
                'branch_id',
                $request->branch_id
            )
            ->where(
                'status',
                'OPEN'
            )
            ->first();

            if ($openPeriod) {

                return response()->json([
                    'success' => false,
                    'message' =>
                        'Ya existe un período abierto para esta sucursal.',
                    'data' => $openPeriod
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | CREAR
            |--------------------------------------------------------------------------
            */

            $accountingPeriod = AccountingPeriod::create([
                'id_company' => $idCompany,
                'branch_id'  => $request->branch_id,
                'type'       => strtoupper($request->type),
                'period'     => $period,
                'start_date' => $startDate,
                'end_date'   => $endDate,
                'status'     => 'OPEN',
                'closed_at'  => null,
                'closed_by'  => null,
            ]);

            /*
            |--------------------------------------------------------------------------
            | RESPONSE
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'success' => true,
                'message' =>
                    'Período contable abierto correctamente.',
                'data' => $accountingPeriod
            ], 201);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' =>
                    'Error al registrar período contable.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | CERRAR PERÍODO
    |--------------------------------------------------------------------------
    */

    public function close($id)
    {
        try {

            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado.'
                ], 401);
            }

            $period = AccountingPeriod::where(
                'id_company',
                $user->id_company
            )
            ->find($id);

            if (!$period) {

                return response()->json([
                    'success' => false,
                    'message' =>
                        'Período contable no encontrado.'
                ], 404);
            }

            if ($period->status === 'CLOSED') {

                return response()->json([
                    'success' => false,
                    'message' =>
                        'El período ya está cerrado.'
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | CERRAR
            |--------------------------------------------------------------------------
            */

            $period->update([
                'status' => 'CLOSED',
                'closed_at' => now(),
                'closed_by' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' =>
                    'Período cerrado correctamente.',
                'data' => $period->fresh()
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' =>
                    'Error al cerrar período.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | REABRIR PERÍODO
    |--------------------------------------------------------------------------
    */

    public function reopen($id)
    {
        try {

            $user = Auth::user();

            if (!$user) {

                return response()->json([
                    'success' => false,
                    'message' =>
                        'Usuario no autenticado.'
                ], 401);
            }

            $period = AccountingPeriod::where(
                'id_company',
                $user->id_company
            )
            ->find($id);

            if (!$period) {

                return response()->json([
                    'success' => false,
                    'message' =>
                        'Período no encontrado.'
                ], 404);
            }

            if ($period->status === 'OPEN') {

                return response()->json([
                    'success' => false,
                    'message' =>
                        'El período ya está abierto.'
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | VALIDAR OTRO ABIERTO
            |--------------------------------------------------------------------------
            */

            $openPeriod = AccountingPeriod::where(
                'id_company',
                $user->id_company
            )
            ->where(
                'branch_id',
                $period->branch_id
            )
            ->where(
                'status',
                'OPEN'
            )
            ->where(
                'id',
                '!=',
                $period->id
            )
            ->first();

            if ($openPeriod) {

                return response()->json([
                    'success' => false,
                    'message' =>
                        'Ya existe otro período abierto para esta sucursal.',
                    'data' => $openPeriod
                ], 422);
            }

            $period->update([
                'status' => 'OPEN',
                'closed_at' => null,
                'closed_by' => null,
            ]);

            return response()->json([
                'success' => true,
                'message' =>
                    'Período reabierto correctamente.',
                'data' => $period->fresh()
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' =>
                    'Error al reabrir período.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | ELIMINAR PERÍODO
    |--------------------------------------------------------------------------
    */

    public function destroy($id)
    {
        try {

            $user = Auth::user();

            $period = AccountingPeriod::where(
                'id_company',
                $user->id_company
            )
            ->find($id);

            if (!$period) {

                return response()->json([
                    'success' => false,
                    'message' =>
                        'Período no encontrado.'
                ], 404);
            }

            /*
            |--------------------------------------------------------------------------
            | SOLO PERMITIR ELIMINAR CERRADOS
            |--------------------------------------------------------------------------
            */

            if ($period->status === 'OPEN') {

                return response()->json([
                    'success' => false,
                    'message' =>
                        'No puedes eliminar un período abierto.'
                ], 422);
            }

            $period->delete();

            return response()->json([
                'success' => true,
                'message' =>
                    'Período eliminado correctamente.'
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' =>
                    'Error al eliminar período.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}