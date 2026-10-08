<?php

namespace App\Domains\Dashboard\Controllers;




use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use Carbon\Carbon;

use App\Domains\Empresa\Models\Branch;

use App\Domains\Inventario\Models\Product;
use App\Domains\Inventario\Models\StockProduct;

use App\Domains\Seguridad\Models\User;

use App\Domains\Ventas\Models\Sale;
use App\Domains\Ventas\Models\SaleDetail;
use App\Domains\Ventas\Models\SalePayment;

use App\Domains\Caja\Models\CashRegisterSession;
use App\Domains\Caja\Models\CashMovement;


class DashboardController extends Controller
{
    /**
     * ============================================================
     * DASHBOARD
     * ============================================================
     *
     * GET /api/dashboard
     *
     * Parámetros:
     *
     * ?period=today
     * ?period=week
     * ?period=month
     * ?period=previous_month
     *
     * ?branch_id=all
     * ?branch_id=1
     */
    public function index(Request $request)
    {
        try {

            /*
            |--------------------------------------------------------------------------
            | USUARIO AUTENTICADO
            |--------------------------------------------------------------------------
            */

            $authUser = Auth::user();

            if (!$authUser) {

                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado.'
                ], 401);
            }


            /*
            |--------------------------------------------------------------------------
            | EMPRESA
            |--------------------------------------------------------------------------
            */

            $idCompany = $authUser->id_company;

            if (!$idCompany) {

                return response()->json([
                    'success' => false,
                    'message' =>
                        'El usuario no tiene una empresa asignada.'
                ], 422);
            }


            /*
            |--------------------------------------------------------------------------
            | ROL
            |--------------------------------------------------------------------------
            */

            $roleId = (int) $authUser->role_id;

            /*
             * ADMIN = 1
             */
            $isAdmin = $roleId === 1;


            /*
            |--------------------------------------------------------------------------
            | USUARIO
            |--------------------------------------------------------------------------
            */

            $user = User::query()
                ->where('id', $authUser->id)
                ->where('id_company', $idCompany)
                ->with([
                    'branches' => function ($query) use ($idCompany) {

                        $query
                            ->where(
                                'branches.id_company',
                                $idCompany
                            )
                            ->where(
                                'branches.status',
                                true
                            )
                            ->orderBy(
                                'branches.branch_name'
                            );
                    }
                ])
                ->first();


            /*
            |--------------------------------------------------------------------------
            | SUCURSALES PERMITIDAS
            |--------------------------------------------------------------------------
            |
            | ADMIN
            |   Todas las sucursales de la empresa.
            |
            | OTROS
            |   Solamente las sucursales asignadas.
            |
            */

            if ($isAdmin) {

                $allowedBranches = Branch::query()
                    ->where(
                        'id_company',
                        $idCompany
                    )
                    ->where(
                        'status',
                        true
                    )
                    ->select([
                        'id',
                        'code_branch',
                        'id_company',
                        'branch_name',
                        'department',
                        'municipality',
                        'address',
                        'contact_name',
                        'phone',
                        'status'
                    ])
                    ->orderBy('branch_name')
                    ->get();

            } else {

                $allowedBranches = $user
                    ? $user->branches
                    : collect();
            }


            /*
            |--------------------------------------------------------------------------
            | IDS DE SUCURSALES PERMITIDAS
            |--------------------------------------------------------------------------
            */

            $allowedBranchIds = $allowedBranches
                ->pluck('id')
                ->map(function ($id) {

                    return (int) $id;

                })
                ->values()
                ->toArray();


            /*
            |--------------------------------------------------------------------------
            | FILTRO DE SUCURSAL
            |--------------------------------------------------------------------------
            */

            $requestedBranch = $request->input(
                'branch_id',
                'all'
            );


            /*
            |--------------------------------------------------------------------------
            | VALIDAR SUCURSAL
            |--------------------------------------------------------------------------
            */

            if (
                $requestedBranch !== 'all' &&
                !in_array(
                    (int) $requestedBranch,
                    $allowedBranchIds
                )
            ) {

                return response()->json([
                    'success' => false,
                    'message' =>
                        'No tienes acceso a la sucursal seleccionada.'
                ], 403);
            }


            /*
            |--------------------------------------------------------------------------
            | SUCURSALES QUE SE CONSULTARÁN
            |--------------------------------------------------------------------------
            */

            $branchIds = $requestedBranch === 'all'
                ? $allowedBranchIds
                : [(int) $requestedBranch];


            /*
            |--------------------------------------------------------------------------
            | PERÍODO
            |--------------------------------------------------------------------------
            */

            $period = $request->input(
                'period',
                'month'
            );

            $allowedPeriods = [
                'today',
                'week',
                'month',
                'previous_month'
            ];

            if (!in_array(
                $period,
                $allowedPeriods
            )) {

                $period = 'month';
            }


            /*
            |--------------------------------------------------------------------------
            | FECHAS
            |--------------------------------------------------------------------------
            */

            $dates = $this->getPeriodDates(
                $period
            );

            $startDate = $dates['start'];

            $endDate = $dates['end'];


            /*
            |--------------------------------------------------------------------------
            | PERÍODO ANTERIOR
            |--------------------------------------------------------------------------
            */

            $previousDates =
                $this->getPreviousPeriodDates(
                    $period,
                    $startDate,
                    $endDate
                );


            /*
            |--------------------------------------------------------------------------
            | RESUMEN DE VENTAS
            |--------------------------------------------------------------------------
            */

            $salesQuery = Sale::query()
                ->where(
                    'sales.id_company',
                    $idCompany
                )
                ->whereBetween(
                    'sales.date',
                    [
                        $startDate,
                        $endDate
                    ]
                );


            if (!empty($branchIds)) {

                $salesQuery->whereIn(
                    'sales.branch_id',
                    $branchIds
                );

            } else {

                /*
                 * Usuario sin sucursales.
                 */
                $salesQuery->whereRaw(
                    '1 = 0'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | TOTAL VENTAS
            |--------------------------------------------------------------------------
            */

            $salesTotal = (float)
                (clone $salesQuery)
                    ->sum('sales.total');


            /*
            |--------------------------------------------------------------------------
            | CANTIDAD DE VENTAS
            |--------------------------------------------------------------------------
            */

            $saleCount = (int)
                (clone $salesQuery)
                    ->count();


            /*
            |--------------------------------------------------------------------------
            | CLIENTES ATENDIDOS
            |--------------------------------------------------------------------------
            */

            $clientsAttended = (int)
                (clone $salesQuery)
                    ->whereNotNull(
                        'client_id'
                    )
                    ->distinct(
                        'client_id'
                    )
                    ->count(
                        'client_id'
                    );


            /*
            |--------------------------------------------------------------------------
            | PRODUCTOS VENDIDOS
            |--------------------------------------------------------------------------
            |
            | quantity - returned_quantity
            |
            */

            $productsSoldQuery =
                SaleDetail::query()
                    ->join(
                        'sales',
                        'sales.id',
                        '=',
                        'sale_details.sale_id'
                    )
                    ->where(
                        'sales.id_company',
                        $idCompany
                    )
                    ->whereBetween(
                        'sales.date',
                        [
                            $startDate,
                            $endDate
                        ]
                    );


            if (!empty($branchIds)) {

                $productsSoldQuery->whereIn(
                    'sales.branch_id',
                    $branchIds
                );

            } else {

                $productsSoldQuery->whereRaw(
                    '1 = 0'
                );
            }


            $productsSold = (float)
                $productsSoldQuery
                    ->selectRaw("
                        COALESCE(
                            SUM(
                                sale_details.quantity
                                -
                                COALESCE(
                                    sale_details.returned_quantity,
                                    0
                                )
                            ),
                            0
                        ) AS total
                    ")
                    ->value('total');


            /*
            |--------------------------------------------------------------------------
            | PERÍODO ANTERIOR - VENTAS
            |--------------------------------------------------------------------------
            */

            $previousSalesQuery =
                Sale::query()
                    ->where(
                        'sales.id_company',
                        $idCompany
                    )
                    ->whereBetween(
                        'sales.date',
                        [
                            $previousDates['start'],
                            $previousDates['end']
                        ]
                    );


            if (!empty($branchIds)) {

                $previousSalesQuery->whereIn(
                    'sales.branch_id',
                    $branchIds
                );

            } else {

                $previousSalesQuery->whereRaw(
                    '1 = 0'
                );
            }


            $previousSalesTotal = (float)
                (clone $previousSalesQuery)
                    ->sum('sales.total');


            $previousSaleCount = (int)
                (clone $previousSalesQuery)
                    ->count();


            $previousClients = (int)
                (clone $previousSalesQuery)
                    ->whereNotNull(
                        'client_id'
                    )
                    ->distinct(
                        'client_id'
                    )
                    ->count(
                        'client_id'
                    );


            /*
            |--------------------------------------------------------------------------
            | PERÍODO ANTERIOR - PRODUCTOS
            |--------------------------------------------------------------------------
            */

            $previousProductsQuery =
                SaleDetail::query()
                    ->join(
                        'sales',
                        'sales.id',
                        '=',
                        'sale_details.sale_id'
                    )
                    ->where(
                        'sales.id_company',
                        $idCompany
                    )
                    ->whereBetween(
                        'sales.date',
                        [
                            $previousDates['start'],
                            $previousDates['end']
                        ]
                    );


            if (!empty($branchIds)) {

                $previousProductsQuery->whereIn(
                    'sales.branch_id',
                    $branchIds
                );

            } else {

                $previousProductsQuery->whereRaw(
                    '1 = 0'
                );
            }


            $previousProductsSold = (float)
                $previousProductsQuery
                    ->selectRaw("
                        COALESCE(
                            SUM(
                                sale_details.quantity
                                -
                                COALESCE(
                                    sale_details.returned_quantity,
                                    0
                                )
                            ),
                            0
                        ) AS total
                    ")
                    ->value('total');


            /*
            |--------------------------------------------------------------------------
            | VARIACIONES
            |--------------------------------------------------------------------------
            */

            $salesVariation =
                $this->calculateVariation(
                    $salesTotal,
                    $previousSalesTotal
                );


            $productsVariation =
                $this->calculateVariation(
                    $productsSold,
                    $previousProductsSold
                );


            $clientsVariation =
                $this->calculateVariation(
                    $clientsAttended,
                    $previousClients
                );


            $saleCountVariation =
                $this->calculateVariation(
                    $saleCount,
                    $previousSaleCount
                );


            /*
            |--------------------------------------------------------------------------
            | MÉTODOS DE PAGO
            |--------------------------------------------------------------------------
            */

            $paymentMethodsQuery =
                SalePayment::query()
                    ->join(
                        'sales',
                        'sales.id',
                        '=',
                        'sale_payments.sale_id'
                    )
                    ->where(
                        'sale_payments.id_company',
                        $idCompany
                    )
                    ->whereBetween(
                        'sales.date',
                        [
                            $startDate,
                            $endDate
                        ]
                    );


            if (!empty($branchIds)) {

                $paymentMethodsQuery->whereIn(
                    'sales.branch_id',
                    $branchIds
                );

            } else {

                $paymentMethodsQuery->whereRaw(
                    '1 = 0'
                );
            }


            $paymentMethods =
                $paymentMethodsQuery
                    ->select(
                        'sale_payments.payment_method',
                        DB::raw(
                            'SUM(sale_payments.amount) as total'
                        ),
                        DB::raw(
                            'COUNT(*) as transactions'
                        )
                    )
                    ->groupBy(
                        'sale_payments.payment_method'
                    )
                    ->orderByDesc(
                        'total'
                    )
                    ->get()
                    ->map(function ($item) {

                        return [

                            'method' =>
                                strtoupper(
                                    (string)
                                    $item->payment_method
                                ),

                            'total' =>
                                (float)
                                $item->total,

                            'transactions' =>
                                (int)
                                $item->transactions
                        ];

                    })
                    ->values();


            /*
            |--------------------------------------------------------------------------
            | VENTAS POR DÍA
            |--------------------------------------------------------------------------
            */

            $salesByDayQuery =
                Sale::query()
                    ->where(
                        'sales.id_company',
                        $idCompany
                    )
                    ->whereBetween(
                        'sales.date',
                        [
                            $startDate,
                            $endDate
                        ]
                    );


            if (!empty($branchIds)) {

                $salesByDayQuery->whereIn(
                    'sales.branch_id',
                    $branchIds
                );

            } else {

                $salesByDayQuery->whereRaw(
                    '1 = 0'
                );
            }


            $salesByDay =
                $salesByDayQuery
                    ->selectRaw("
                        DATE(sales.date) as sale_date,
                        SUM(sales.total) as total,
                        COUNT(sales.id) as sales_count
                    ")
                    ->groupBy(
                        DB::raw(
                            'DATE(sales.date)'
                        )
                    )
                    ->orderBy(
                        'sale_date'
                    )
                    ->get()
                    ->map(function ($item) {

                        return [

                            'date' =>
                                $item->sale_date,

                            'label' =>
                                Carbon::parse(
                                    $item->sale_date
                                )->format('d/m'),

                            'total' =>
                                (float)
                                $item->total,

                            'sales_count' =>
                                (int)
                                $item->sales_count
                        ];

                    })
                    ->values();


            /*
            |--------------------------------------------------------------------------
            | TOP PRODUCTOS
            |--------------------------------------------------------------------------
            */

            $topProductsQuery =
                SaleDetail::query()
                    ->join(
                        'sales',
                        'sales.id',
                        '=',
                        'sale_details.sale_id'
                    )
                    ->join(
                        'products',
                        'products.id',
                        '=',
                        'sale_details.product_id'
                    )
                    ->where(
                        'sales.id_company',
                        $idCompany
                    )
                    ->whereBetween(
                        'sales.date',
                        [
                            $startDate,
                            $endDate
                        ]
                    );


            if (!empty($branchIds)) {

                $topProductsQuery->whereIn(
                    'sales.branch_id',
                    $branchIds
                );

            } else {

                $topProductsQuery->whereRaw(
                    '1 = 0'
                );
            }


            $topProducts =
                $topProductsQuery
                    ->select(
                        'products.id',
                        'products.code_product',
                        'products.name',

                        DB::raw("
                            SUM(
                                sale_details.quantity
                                -
                                COALESCE(
                                    sale_details.returned_quantity,
                                    0
                                )
                            ) as quantity
                        "),

                        DB::raw("
                            SUM(
                                sale_details.total
                            ) as total
                        ")
                    )
                    ->groupBy(
                        'products.id',
                        'products.code_product',
                        'products.name'
                    )
                    ->orderByDesc(
                        'quantity'
                    )
                    ->limit(10)
                    ->get()
                    ->map(function ($item) {

                        return [

                            'id' =>
                                $item->id,

                            'code' =>
                                $item->code_product,

                            'name' =>
                                $item->name,

                            'quantity' =>
                                (float)
                                $item->quantity,

                            'total' =>
                                (float)
                                $item->total
                        ];

                    })
                    ->values();


            /*
            |--------------------------------------------------------------------------
            | INVENTARIO
            |--------------------------------------------------------------------------
            */

            /*
             * Como actualmente no tenemos minimum_stock,
             * usamos 5 como límite de stock bajo.
             */

            $lowStockThreshold = 5;


            $inventoryQuery =
                StockProduct::query()
                    ->where(
                        'product_stock.id_company',
                        $idCompany
                    )
                    ->where(
                        'product_stock.status',
                        true
                    );


            if (!empty($branchIds)) {

                $inventoryQuery->whereIn(
                    'product_stock.branch_id',
                    $branchIds
                );

            } else {

                $inventoryQuery->whereRaw(
                    '1 = 0'
                );
            }


            $inventoryRows =
                $inventoryQuery
                    ->select(
                        'product_stock.product_id',
                        DB::raw(
                            'SUM(product_stock.current_stock) as stock'
                        )
                    )
                    ->groupBy(
                        'product_stock.product_id'
                    )
                    ->get();


            $inventoryNormal = 0;
            $inventoryLow = 0;
            $inventoryOut = 0;


            foreach ($inventoryRows as $row) {

                $stock = (float)
                    $row->stock;


                if ($stock <= 0) {

                    $inventoryOut++;

                } elseif (
                    $stock <= $lowStockThreshold
                ) {

                    $inventoryLow++;

                } else {

                    $inventoryNormal++;
                }
            }


            /*
            |--------------------------------------------------------------------------
            | CAJA
            |--------------------------------------------------------------------------
            */

            $cashData =
                $this->getCashData(
                    $idCompany,
                    $branchIds,
                    $isAdmin,
                    $authUser->id
                );


            /*
            |--------------------------------------------------------------------------
            | ÚLTIMAS VENTAS
            |--------------------------------------------------------------------------
            */

            $latestSalesQuery =
                Sale::query()
                    ->with([
                        'client:id,name',
                        'user:id,name',
                        'branch:id,branch_name'
                    ])
                    ->where(
                        'sales.id_company',
                        $idCompany
                    )
                    ->whereBetween(
                        'sales.date',
                        [
                            $startDate,
                            $endDate
                        ]
                    );


            if (!empty($branchIds)) {

                $latestSalesQuery->whereIn(
                    'sales.branch_id',
                    $branchIds
                );

            } else {

                $latestSalesQuery->whereRaw(
                    '1 = 0'
                );
            }


            $latestSales =
                $latestSalesQuery
                    ->orderByDesc(
                        'sales.date'
                    )
                    ->limit(10)
                    ->get()
                    ->map(function ($sale) {

                        /*
                        |--------------------------------------------------------------------------
                        | PAGOS DE LA VENTA
                        |--------------------------------------------------------------------------
                        */

                        $payments =
                            SalePayment::query()
                                ->where(
                                    'sale_id',
                                    $sale->id
                                )
                                ->get();


                        $paymentNames =
                            $payments
                                ->pluck(
                                    'payment_method'
                                )
                                ->filter()
                                ->unique()
                                ->map(function ($method) {

                                    return match (
                                        strtoupper(
                                            $method
                                        )
                                    ) {

                                        'CASH' =>
                                            'Efectivo',

                                        'CARD' =>
                                            'Tarjeta',

                                        'QR' =>
                                            'QR',

                                        'TRANSFER' =>
                                            'Transferencia',

                                        'CHECK' =>
                                            'Cheque',

                                        'OTHER' =>
                                            'Otro',

                                        default =>
                                            $method
                                    };

                                })
                                ->values()
                                ->implode(
                                    ' + '
                                );


                        return [

                            'id' =>
                                $sale->id,

                            'code' =>
                                $sale->code_sale,

                            'receipt_number' =>
                                $sale->receipt_number,

                            'date' =>
                                $sale->date,

                            'client' =>
                                $sale->client
                                    ? $sale->client->name
                                    : 'Cliente general',

                            'seller' =>
                                $sale->user
                                    ? $sale->user->name
                                    : null,

                            'branch' =>
                                $sale->branch
                                    ? $sale->branch->branch_name
                                    : null,

                            'total' =>
                                (float)
                                $sale->total,

                            'payment' =>
                                $paymentNames
                                    ?: 'Sin pago'
                        ];
                    })
                    ->values();


            /*
            |--------------------------------------------------------------------------
            | VENTAS POR SUCURSAL
            |--------------------------------------------------------------------------
            */

            $salesByBranchQuery =
                Sale::query()
                    ->join(
                        'branches',
                        'branches.id',
                        '=',
                        'sales.branch_id'
                    )
                    ->where(
                        'sales.id_company',
                        $idCompany
                    )
                    ->whereBetween(
                        'sales.date',
                        [
                            $startDate,
                            $endDate
                        ]
                    );


            if (!empty($branchIds)) {

                $salesByBranchQuery->whereIn(
                    'sales.branch_id',
                    $branchIds
                );

            } else {

                $salesByBranchQuery->whereRaw(
                    '1 = 0'
                );
            }


            $salesByBranch =
                $salesByBranchQuery
                    ->select(
                        'branches.id',
                        'branches.code_branch',
                        'branches.branch_name',

                        DB::raw(
                            'SUM(sales.total) as total'
                        ),

                        DB::raw(
                            'COUNT(sales.id) as sales_count'
                        )
                    )
                    ->groupBy(
                        'branches.id',
                        'branches.code_branch',
                        'branches.branch_name'
                    )
                    ->orderByDesc(
                        'total'
                    )
                    ->get()
                    ->map(function ($branch) {

                        return [

                            'id' =>
                                $branch->id,

                            'code' =>
                                $branch->code_branch,

                            'name' =>
                                $branch->branch_name,

                            'total' =>
                                (float)
                                $branch->total,

                            'sales_count' =>
                                (int)
                                $branch->sales_count
                        ];

                    })
                    ->values();


            /*
            |--------------------------------------------------------------------------
            | EMPRESA
            |--------------------------------------------------------------------------
            */

            $company = null;

            if (
                method_exists(
                    $authUser,
                    'company'
                )
            ) {

                $company =
                    $authUser->company;
            }


            /*
            |--------------------------------------------------------------------------
            | ROL
            |--------------------------------------------------------------------------
            */

            $role = null;

            if (
                method_exists(
                    $authUser,
                    'role'
                )
            ) {

                $role =
                    $authUser->role;
            }


            /*
            |--------------------------------------------------------------------------
            | RESPUESTA
            |--------------------------------------------------------------------------
            */

            return response()->json([

                'success' => true,

                'message' =>
                    'Dashboard cargado correctamente.',

                'data' => [

                    /*
                    |--------------------------------------------------------------------------
                    | USUARIO
                    |--------------------------------------------------------------------------
                    */

                    'user' => [

                        'id' =>
                            $authUser->id,

                        'name' =>
                            $authUser->name,

                        'email' =>
                            $authUser->email,

                        'role_id' =>
                            $roleId,

                        'is_admin' =>
                            $isAdmin,

                        'id_company' =>
                            $idCompany
                    ],


                    /*
                    |--------------------------------------------------------------------------
                    | ROL
                    |--------------------------------------------------------------------------
                    */

                    'role' =>
                        $role,


                    /*
                    |--------------------------------------------------------------------------
                    | EMPRESA
                    |--------------------------------------------------------------------------
                    */

                    'company' => $company,


                    /*
                    |--------------------------------------------------------------------------
                    | SUCURSALES
                    |--------------------------------------------------------------------------
                    */

                    'branches' =>
                        $allowedBranches
                            ->values(),


                    /*
                    |--------------------------------------------------------------------------
                    | FILTROS
                    |--------------------------------------------------------------------------
                    */

                    'filters' => [

                        'period' =>
                            $period,

                        'branch_id' =>
                            $requestedBranch,

                        'start' =>
                            $startDate
                                ->toDateTimeString(),

                        'end' =>
                            $endDate
                                ->toDateTimeString()
                    ],


                    /*
                    |--------------------------------------------------------------------------
                    | RESUMEN
                    |--------------------------------------------------------------------------
                    */

                    'summary' => [

                        'sales' => [

                            'total' =>
                                round(
                                    $salesTotal,
                                    2
                                ),

                            'variation' =>
                                $salesVariation
                        ],


                        'products_sold' => [

                            'total' =>
                                round(
                                    $productsSold,
                                    2
                                ),

                            'variation' =>
                                $productsVariation
                        ],


                        'clients_attended' => [

                            'total' =>
                                $clientsAttended,

                            'variation' =>
                                $clientsVariation
                        ],


                        'sale_count' => [

                            'total' =>
                                $saleCount,

                            'variation' =>
                                $saleCountVariation
                        ]
                    ],


                    /*
                    |--------------------------------------------------------------------------
                    | CAJA
                    |--------------------------------------------------------------------------
                    */

                    'cash' =>
                        $cashData,


                    /*
                    |--------------------------------------------------------------------------
                    | MÉTODOS DE PAGO
                    |--------------------------------------------------------------------------
                    */

                    'payment_methods' =>
                        $paymentMethods,


                    /*
                    |--------------------------------------------------------------------------
                    | VENTAS POR DÍA
                    |--------------------------------------------------------------------------
                    */

                    'sales_by_day' =>
                        $salesByDay,


                    /*
                    |--------------------------------------------------------------------------
                    | PRODUCTOS MÁS VENDIDOS
                    |--------------------------------------------------------------------------
                    */

                    'top_products' =>
                        $topProducts,


                    /*
                    |--------------------------------------------------------------------------
                    | INVENTARIO
                    |--------------------------------------------------------------------------
                    */

                    'inventory' => [

                        'normal' =>
                            $inventoryNormal,

                        'low' =>
                            $inventoryLow,

                        'out' =>
                            $inventoryOut,

                        'low_stock_threshold' =>
                            $lowStockThreshold
                    ],


                    /*
                    |--------------------------------------------------------------------------
                    | ÚLTIMAS VENTAS
                    |--------------------------------------------------------------------------
                    */

                    'latest_sales' =>
                        $latestSales,


                    /*
                    |--------------------------------------------------------------------------
                    | VENTAS POR SUCURSAL
                    |--------------------------------------------------------------------------
                    */

                    'sales_by_branch' =>
                        $salesByBranch
                ]

            ]);

        } catch (\Throwable $e) {

            return response()->json([

                'success' => false,

                'message' =>
                    'Error al cargar el dashboard.',

                'error' =>
                    $e->getMessage(),

                'file' =>
                    $e->getFile(),

                'line' =>
                    $e->getLine()

            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | OBTENER FECHAS DEL PERÍODO
    |--------------------------------------------------------------------------
    */

    private function getPeriodDates(
        string $period
    ): array {

        $now = Carbon::now();


        switch ($period) {

            case 'today':

                return [

                    'start' =>
                        $now
                            ->copy()
                            ->startOfDay(),

                    'end' =>
                        $now
                            ->copy()
                            ->endOfDay()
                ];


            case 'week':

                return [

                    'start' =>
                        $now
                            ->copy()
                            ->startOfWeek(),

                    'end' =>
                        $now
                            ->copy()
                            ->endOfWeek()
                ];


            case 'previous_month':

                return [

                    'start' =>
                        $now
                            ->copy()
                            ->subMonthNoOverflow()
                            ->startOfMonth(),

                    'end' =>
                        $now
                            ->copy()
                            ->subMonthNoOverflow()
                            ->endOfMonth()
                ];


            case 'month':

            default:

                return [

                    'start' =>
                        $now
                            ->copy()
                            ->startOfMonth(),

                    'end' =>
                        $now
                            ->copy()
                            ->endOfMonth()
                ];
        }
    }


    /*
    |--------------------------------------------------------------------------
    | OBTENER PERÍODO ANTERIOR
    |--------------------------------------------------------------------------
    */

    private function getPreviousPeriodDates(
        string $period,
        Carbon $start,
        Carbon $end
    ): array {

        switch ($period) {

            case 'today':

                return [

                    'start' =>
                        $start
                            ->copy()
                            ->subDay()
                            ->startOfDay(),

                    'end' =>
                        $start
                            ->copy()
                            ->subDay()
                            ->endOfDay()
                ];


            case 'week':

                return [

                    'start' =>
                        $start
                            ->copy()
                            ->subWeek(),

                    'end' =>
                        $end
                            ->copy()
                            ->subWeek()
                ];


            case 'previous_month':

                return [

                    'start' =>
                        $start
                            ->copy()
                            ->subMonthNoOverflow()
                            ->startOfMonth(),

                    'end' =>
                        $start
                            ->copy()
                            ->subMonthNoOverflow()
                            ->endOfMonth()
                ];


            case 'month':

            default:

                return [

                    'start' =>
                        $start
                            ->copy()
                            ->subMonthNoOverflow()
                            ->startOfMonth(),

                    'end' =>
                        $start
                            ->copy()
                            ->subMonthNoOverflow()
                            ->endOfMonth()
                ];
        }
    }


    /*
    |--------------------------------------------------------------------------
    | CALCULAR VARIACIÓN
    |--------------------------------------------------------------------------
    */

    private function calculateVariation(
        float $current,
        float $previous
    ): float {

        if ($previous == 0) {

            if ($current == 0) {
                return 0;
            }

            return 100;
        }


        return round(
            (
                ($current - $previous)
                /
                $previous
            ) * 100,
            2
        );
    }


    /*
    |--------------------------------------------------------------------------
    | DATOS DE CAJA
    |--------------------------------------------------------------------------
    */

    private function getCashData(
        string $idCompany,
        array $branchIds,
        bool $isAdmin,
        int $userId
    ): array {


        /*
        |--------------------------------------------------------------------------
        | SESIONES ABIERTAS
        |--------------------------------------------------------------------------
        */

        $sessionsQuery =
            CashRegisterSession::query()
                ->where(
                    'cash_register_sessions.id_company',
                    $idCompany
                )
                ->where(
                    'cash_register_sessions.status',
                    'OPEN'
                );


        /*
        |--------------------------------------------------------------------------
        | FILTRO POR SUCURSAL
        |--------------------------------------------------------------------------
        */

        if (!empty($branchIds)) {

            $sessionsQuery->whereIn(
                'cash_register_sessions.branch_id',
                $branchIds
            );

        } else {

            $sessionsQuery->whereRaw(
                '1 = 0'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | USUARIO NORMAL
        |--------------------------------------------------------------------------
        |
        | Solamente su sesión de caja.
        |
        */

        if (!$isAdmin) {

            $sessionsQuery->where(
                'cash_register_sessions.user_id',
                $userId
            );
        }


        /*
        |--------------------------------------------------------------------------
        | OBTENER SESIONES
        |--------------------------------------------------------------------------
        */

        $sessions =
            $sessionsQuery->get();


        /*
        |--------------------------------------------------------------------------
        | SIN SESIONES ABIERTAS
        |--------------------------------------------------------------------------
        */

        if ($sessions->isEmpty()) {

            return [

                'sessions' =>
                    0,

                'sales_cash' =>
                    0,

                'income' =>
                    0,

                'expenses' =>
                    0,

                'opening' =>
                    0,

                'physical_expected' =>
                    0
            ];
        }


        /*
        |--------------------------------------------------------------------------
        | ACUMULADORES
        |--------------------------------------------------------------------------
        */

        $salesCash = 0;

        $income = 0;

        $expenses = 0;

        $opening = 0;

        $physicalExpected = 0;


        /*
        |--------------------------------------------------------------------------
        | RECORRER SESIONES
        |--------------------------------------------------------------------------
        */

        foreach ($sessions as $session) {


            /*
            |--------------------------------------------------------------------------
            | APERTURA
            |--------------------------------------------------------------------------
            */

            $sessionOpening =
                (float)
                $session->opening_amount;

            $opening +=
                $sessionOpening;


            /*
            |--------------------------------------------------------------------------
            | VENTAS EN EFECTIVO
            |--------------------------------------------------------------------------
            */

            $cashSales =
                SalePayment::query()
                    ->where(
                        'cash_register_session_id',
                        $session->id
                    )
                    ->where(
                        'id_company',
                        $idCompany
                    )
                    ->whereRaw(
                        'UPPER(payment_method) = ?',
                        ['CASH']
                    )
                    ->sum('amount');


            $cashSales =
                (float)
                $cashSales;


            $salesCash +=
                $cashSales;


            /*
            |--------------------------------------------------------------------------
            | INGRESOS
            |--------------------------------------------------------------------------
            */

            $cashIn =
                CashMovement::query()
                    ->where(
                        'cash_register_session_id',
                        $session->id
                    )
                    ->where(
                        'id_company',
                        $idCompany
                    )
                    ->where(
                        'type',
                        'INGRESO'
                    )
                    ->sum('amount');


            $cashIn =
                (float)
                $cashIn;


            $income +=
                $cashIn;


            /*
            |--------------------------------------------------------------------------
            | EGRESOS
            |--------------------------------------------------------------------------
            */

            $cashOut =
                CashMovement::query()
                    ->where(
                        'cash_register_session_id',
                        $session->id
                    )
                    ->where(
                        'id_company',
                        $idCompany
                    )
                    ->where(
                        'type',
                        'EGRESO'
                    )
                    ->sum('amount');


            $cashOut =
                (float)
                $cashOut;


            $expenses +=
                $cashOut;


            /*
            |--------------------------------------------------------------------------
            | EFECTIVO FÍSICO ESPERADO
            |--------------------------------------------------------------------------
            */

            $physicalExpected +=
                $sessionOpening
                +
                $cashSales
                +
                $cashIn
                -
                $cashOut;
        }


        /*
        |--------------------------------------------------------------------------
        | RESPUESTA
        |--------------------------------------------------------------------------
        */

        return [

            'sessions' =>
                $sessions->count(),

            'sales_cash' =>
                round(
                    $salesCash,
                    2
                ),

            'income' =>
                round(
                    $income,
                    2
                ),

            'expenses' =>
                round(
                    $expenses,
                    2
                ),

            'opening' =>
                round(
                    $opening,
                    2
                ),

            'physical_expected' =>
                round(
                    $physicalExpected,
                    2
                )
        ];
    }
}