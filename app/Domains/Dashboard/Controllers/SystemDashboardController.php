<?php

namespace App\Domains\Dashboard\Controllers;



use App\Http\Controllers\Controller;

use App\Domains\Empresa\Models\Company;
use App\Domains\Seguridad\Models\User;
use App\Domains\Clientes\Models\Client;
use App\Domains\Inventario\Models\Product;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class SystemDashboardController extends Controller
{
    /**
     * Correo autorizado para visualizar
     * el dashboard general de todo DISAAT.
     */
    private const SYSTEM_EMAIL = 'disaat@disaat.com';


    /**
     * Dashboard general del sistema.
     */
    public function index(Request $request)
    {
        $authUser = Auth::user();

        /*
        |--------------------------------------------------------------------------
        | Seguridad
        |--------------------------------------------------------------------------
        */

        if (!$authUser) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        if (
            strtolower(trim($authUser->email)) !==
            strtolower(self::SYSTEM_EMAIL)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para acceder al dashboard del sistema.',
            ], 403);
        }


        /*
        |--------------------------------------------------------------------------
        | Fechas
        |--------------------------------------------------------------------------
        */

        $now = Carbon::now();

        $startMonth = $now->copy()
            ->startOfMonth();

        $endMonth = $now->copy()
            ->endOfMonth();

        $startPreviousMonth = $now->copy()
            ->subMonth()
            ->startOfMonth();

        $endPreviousMonth = $now->copy()
            ->subMonth()
            ->endOfMonth();


        /*
        |--------------------------------------------------------------------------
        | EMPRESAS
        |--------------------------------------------------------------------------
        */

        $totalCompanies = Company::count();

        $companiesThisMonth = Company::query()
            ->whereBetween(
                'created_at',
                [
                    $startMonth,
                    $endMonth
                ]
            )
            ->count();

        $companiesPreviousMonth = Company::query()
            ->whereBetween(
                'created_at',
                [
                    $startPreviousMonth,
                    $endPreviousMonth
                ]
            )
            ->count();


        /*
        |--------------------------------------------------------------------------
        | USUARIOS
        |--------------------------------------------------------------------------
        */

        $totalUsers = User::count();

        $usersThisMonth = User::query()
            ->whereBetween(
                'created_at',
                [
                    $startMonth,
                    $endMonth
                ]
            )
            ->count();

        $usersPreviousMonth = User::query()
            ->whereBetween(
                'created_at',
                [
                    $startPreviousMonth,
                    $endPreviousMonth
                ]
            )
            ->count();


        /*
        |--------------------------------------------------------------------------
        | CLIENTES
        |--------------------------------------------------------------------------
        */

        $totalClients = Client::count();

        $clientsThisMonth = Client::query()
            ->whereBetween(
                'created_at',
                [
                    $startMonth,
                    $endMonth
                ]
            )
            ->count();

        $clientsPreviousMonth = Client::query()
            ->whereBetween(
                'created_at',
                [
                    $startPreviousMonth,
                    $endPreviousMonth
                ]
            )
            ->count();


        /*
        |--------------------------------------------------------------------------
        | PRODUCTOS
        |--------------------------------------------------------------------------
        */

        $totalProducts = Product::count();

        $productsThisMonth = Product::query()
            ->whereBetween(
                'created_at',
                [
                    $startMonth,
                    $endMonth
                ]
            )
            ->count();

        $productsPreviousMonth = Product::query()
            ->whereBetween(
                'created_at',
                [
                    $startPreviousMonth,
                    $endPreviousMonth
                ]
            )
            ->count();


        /*
        |--------------------------------------------------------------------------
        | ESTADO DE EMPRESAS
        |--------------------------------------------------------------------------
        |
        | Si companies.status existe, utilizamos:
        |
        | 1 = activa
        | 0 = inactiva
        |
        | Si además existe algún campo para suspensión,
        | puede ampliarse posteriormente.
        |
        */

        $activeCompanies = 0;
        $inactiveCompanies = 0;
        $suspendedCompanies = 0;

        if (Schema::hasColumn('companies', 'status')) {

            $activeCompanies = Company::query()
                ->where('status', true)
                ->count();

            $inactiveCompanies = Company::query()
                ->where('status', false)
                ->count();

            /*
             * Si posteriormente agregas un estado
             * específico de SUSPENDIDA se puede utilizar.
             */
            if (Schema::hasColumn('companies', 'status_name')) {

                $suspendedCompanies = Company::query()
                    ->whereRaw(
                        'UPPER(status_name) = ?',
                        ['SUSPENDIDA']
                    )
                    ->count();

                $inactiveCompanies = Company::query()
                    ->where('status', false)
                    ->where(function ($query) {

                        $query
                            ->whereNull('status_name')
                            ->orWhereRaw(
                                'UPPER(status_name) != ?',
                                ['SUSPENDIDA']
                            );

                    })
                    ->count();
            }

        } else {

            /*
             * Tu modelo Company actualmente no tiene
             * status en los campos conocidos.
             *
             * Por seguridad consideramos todas
             * las empresas como activas.
             */
            $activeCompanies = $totalCompanies;

        }


        /*
        |--------------------------------------------------------------------------
        | CRECIMIENTO DE EMPRESAS
        |--------------------------------------------------------------------------
        */

        $companyGrowthRaw = Company::query()
            ->selectRaw(
                'YEAR(created_at) as year,
                 MONTH(created_at) as month,
                 COUNT(*) as total'
            )
            ->where(
                'created_at',
                '>=',
                $now->copy()->subMonths(8)->startOfMonth()
            )
            ->groupBy(
                DB::raw('YEAR(created_at)'),
                DB::raw('MONTH(created_at)')
            )
            ->orderBy('year')
            ->orderBy('month')
            ->get();


        /*
        |--------------------------------------------------------------------------
        | Crear los últimos 9 meses aunque tengan 0 empresas.
        |--------------------------------------------------------------------------
        */

        $growth = [];

        for ($i = 8; $i >= 0; $i--) {

            $date = $now->copy()
                ->subMonths($i);

            $year = $date->year;
            $month = $date->month;

            $row = $companyGrowthRaw->first(function ($item) use (
                $year,
                $month
            ) {

                return
                    (int) $item->year === $year &&
                    (int) $item->month === $month;

            });

            $growth[] = [

                'label' => $this->monthLabel(
                    $month
                ),

                'month' => $month,

                'year' => $year,

                'value' => $row
                    ? (int) $row->total
                    : 0,

            ];

        }


        /*
        |--------------------------------------------------------------------------
        | ACTIVIDAD DEL MES
        |--------------------------------------------------------------------------
        */

        $activity = [

            'companies' => $companiesThisMonth,

            'users' => $usersThisMonth,

            'clients' => $clientsThisMonth,

            'products' => $productsThisMonth,

        ];


        /*
        |--------------------------------------------------------------------------
        | EMPRESAS RECIENTES
        |--------------------------------------------------------------------------
        */

        $recentCompanies = Company::query()
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();


        /*
        |--------------------------------------------------------------------------
        | Cantidades por empresa
        |--------------------------------------------------------------------------
        */

        $companyIds = $recentCompanies
            ->pluck('id_company')
            ->values();


        $usersByCompany = User::query()
            ->select(
                'id_company',
                DB::raw('COUNT(*) as total')
            )
            ->whereIn(
                'id_company',
                $companyIds
            )
            ->groupBy('id_company')
            ->pluck(
                'total',
                'id_company'
            );


        $clientsByCompany = Client::query()
            ->select(
                'id_company',
                DB::raw('COUNT(*) as total')
            )
            ->whereIn(
                'id_company',
                $companyIds
            )
            ->groupBy('id_company')
            ->pluck(
                'total',
                'id_company'
            );


        $productsByCompany = Product::query()
            ->select(
                'id_company',
                DB::raw('COUNT(*) as total')
            )
            ->whereIn(
                'id_company',
                $companyIds
            )
            ->groupBy('id_company')
            ->pluck(
                'total',
                'id_company'
            );


        $recentCompaniesData = $recentCompanies
            ->map(function ($company) use (
                $usersByCompany,
                $clientsByCompany,
                $productsByCompany
            ) {

                $status = 'Activa';

                if (
                    Schema::hasColumn(
                        'companies',
                        'status'
                    )
                ) {

                    $status =
                        $company->status
                            ? 'Activa'
                            : 'Inactiva';

                }

                return [

                    'id_company' =>
                        $company->id_company,

                    'name' =>
                        $company->business_name,

                    'users' =>
                        (int) (
                            $usersByCompany[
                                $company->id_company
                            ] ?? 0
                        ),

                    'clients' =>
                        (int) (
                            $clientsByCompany[
                                $company->id_company
                            ] ?? 0
                        ),

                    'products' =>
                        (int) (
                            $productsByCompany[
                                $company->id_company
                            ] ?? 0
                        ),

                    'status' =>
                        $status,

                    'created_at' =>
                        $company->created_at,

                ];

            })
            ->values();


        /*
        |--------------------------------------------------------------------------
        | VARIACIONES
        |--------------------------------------------------------------------------
        */

        $companiesVariation = $this->variation(
            $companiesThisMonth,
            $companiesPreviousMonth
        );

        $usersVariation = $this->variation(
            $usersThisMonth,
            $usersPreviousMonth
        );

        $clientsVariation = $this->variation(
            $clientsThisMonth,
            $clientsPreviousMonth
        );

        $productsVariation = $this->variation(
            $productsThisMonth,
            $productsPreviousMonth
        );


        /*
        |--------------------------------------------------------------------------
        | RESPUESTA
        |--------------------------------------------------------------------------
        */

        return response()->json([

            'success' => true,

            'message' =>
                'Dashboard general cargado correctamente.',

            'data' => [

                'system' => [

                    'name' => 'DISAAT',

                    'email' =>
                        self::SYSTEM_EMAIL,

                    'period' => [
                        'start' =>
                            $startMonth
                                ->toDateString(),

                        'end' =>
                            $endMonth
                                ->toDateString(),
                    ],

                ],

                'summary' => [

                    'companies' => [

                        'total' =>
                            $totalCompanies,

                        'this_month' =>
                            $companiesThisMonth,

                        'variation' =>
                            $companiesVariation,

                    ],

                    'users' => [

                        'total' =>
                            $totalUsers,

                        'this_month' =>
                            $usersThisMonth,

                        'variation' =>
                            $usersVariation,

                    ],

                    'clients' => [

                        'total' =>
                            $totalClients,

                        'this_month' =>
                            $clientsThisMonth,

                        'variation' =>
                            $clientsVariation,

                    ],

                    'products' => [

                        'total' =>
                            $totalProducts,

                        'this_month' =>
                            $productsThisMonth,

                        'variation' =>
                            $productsVariation,

                    ],

                ],

                'company_status' => [

                    'active' =>
                        $activeCompanies,

                    'suspended' =>
                        $suspendedCompanies,

                    'inactive' =>
                        $inactiveCompanies,

                    'total' =>
                        $totalCompanies,

                ],

                'growth' =>
                    $growth,

                'activity' => [

                    'companies' => [

                        'total' =>
                            $companiesThisMonth,

                        'variation' =>
                            $companiesVariation,

                    ],

                    'users' => [

                        'total' =>
                            $usersThisMonth,

                        'variation' =>
                            $usersVariation,

                    ],

                    'clients' => [

                        'total' =>
                            $clientsThisMonth,

                        'variation' =>
                            $clientsVariation,

                    ],

                    'products' => [

                        'total' =>
                            $productsThisMonth,

                        'variation' =>
                            $productsVariation,

                    ],

                ],

                'recent_companies' =>
                    $recentCompaniesData,

            ],

        ]);

    }


    /*
    |--------------------------------------------------------------------------
    | Variación porcentual
    |--------------------------------------------------------------------------
    */

    private function variation(
        int $current,
        int $previous
    ): float {

        if ($previous === 0) {

            if ($current === 0) {
                return 0;
            }

            return 100;
        }

        return round(
            (
                ($current - $previous)
                / $previous
            ) * 100,
            1
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Nombre del mes
    |--------------------------------------------------------------------------
    */

    private function monthLabel(int $month): string
    {
        $months = [

            1 => 'Ene',
            2 => 'Feb',
            3 => 'Mar',
            4 => 'Abr',
            5 => 'May',
            6 => 'Jun',
            7 => 'Jul',
            8 => 'Ago',
            9 => 'Sep',
            10 => 'Oct',
            11 => 'Nov',
            12 => 'Dic',

        ];

        return $months[$month] ?? '';
    }
}