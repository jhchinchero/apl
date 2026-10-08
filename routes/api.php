<?php


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Domains\Seguridad\Controllers\RolesController;
use App\Domains\Seguridad\Controllers\AuthController;
use App\Domains\Seguridad\Controllers\ModuleController;
use App\Http\Controllers\SetupController;
use App\Domains\Empresa\Controllers\BranchController;
use App\Domains\Seguridad\Controllers\UserController;
use App\Domains\Inventario\Controllers\SupplierController;
use App\Domains\Inventario\Controllers\CategoryController;
use App\Domains\Inventario\Controllers\BrandController;
use App\Domains\Inventario\Controllers\ProductController;
use App\Domains\Inventario\Controllers\TypeDataController;
use App\Domains\Inventario\Controllers\UnitController;
use App\Domains\Clientes\Controllers\ClientController;
use App\Domains\Inventario\Controllers\StockProductController;
use App\Domains\Inventario\Controllers\StockMovementController;
use App\Domains\Ventas\Controllers\SaleController;


use App\Domains\Empresa\Controllers\PointOfSaleController;

use App\Domains\Seguridad\Controllers\AccessController;



use App\Domains\Caja\Controllers\CashRegisterController;
use App\Domains\Caja\Controllers\CashRegisterAssignmentController;
use App\Domains\Caja\Controllers\CashRegisterSessionController;


use App\Domains\Empresa\Controllers\CompanyController;

use App\Domains\Ventas\Controllers\SaleReturnController;


use App\Domains\Seguridad\Controllers\UserContextController;
use App\Domains\Inventario\Controllers\ProductTransferController;

use App\Domains\Contabilidad\Controllers\AccountingPeriodController;
use App\Domains\Contabilidad\Controllers\AccountingAccountController;

use App\Domains\Contabilidad\Controllers\AccountingEntryController;


use App\Domains\Contabilidad\Controllers\AccountingJournalController;
use App\Domains\Contabilidad\Controllers\AccountingLedgerController;



use App\Domains\Caja\Controllers\CashCategoryController;

use App\Domains\Contabilidad\Controllers\IncomeExpenseController;

use App\Domains\Empresa\Controllers\CompanyUserController;
use App\Domains\Cotizacion\Controllers\QuotationController;
use App\Domains\Seguridad\Controllers\AccessModulesUserController;

use App\Domains\Seguridad\Controllers\AccessModulesUserJsonController;

use App\Domains\Contabilidad\Controllers\AccountingAccountTypeController;

use App\Domains\Contabilidad\Controllers\AccountingAccountMappingController;

use App\Domains\Inventario\Controllers\StockImportController;

use App\Domains\Inventario\Controllers\ProductReportController;

use App\Domains\Dashboard\Controllers\DashboardController;
use App\Domains\Dashboard\Controllers\SystemDashboardController;
use App\Domains\Facturacion\Controllers\Catalogos\SiatSincronizacionController;
use App\Domains\Facturacion\Controllers\SiatController;
use App\Domains\Facturacion\Controllers\SiatConfiguracionController;
use App\Domains\Facturacion\Controllers\SiatCertificadoController;


use App\Domains\Facturacion\Controllers\CuisController;
use App\Domains\Facturacion\Controllers\Cufd\CufdController;

use App\Domains\Facturacion\Controllers\FacturaEmitirController;


use App\Domains\Caja\Controllers\card_ingresos_egresos\CardIngresosEgresos;
use App\Domains\Caja\Controllers\CashRegister\UserCashRegisterController;
use App\Domains\Facturacion\Controllers\FacturaController;
use App\Domains\Facturacion\Controllers\Homologacion\HomologacionProductoController;


use App\Domains\Contabilidad\Controllers\Seeder\AccountingConfigurationSeederController;

use App\Domains\Caja\Controllers\CashMovementController;

Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Movimientos de caja
    |--------------------------------------------------------------------------
    */

  Route::get(
    '/sale-payments/pending-collections',
    [CashMovementController::class, 'pendingCollections']
);

    /*
    |--------------------------------------------------------------------------
    | Cobrar movimientos
    |--------------------------------------------------------------------------
    */

    /* Route::post(
        '/cash-movements/collect',
        [CashMovementController::class, 'collect']
    ); */
    Route::post(
    '/sale-payments/collect',
    [CashMovementController::class, 'collect']
);

});

Route::get('/test', function () {
    return response()->json([
        'ok' => true,
        'message' => 'Laravel API funcionando'
    ]);
});

Route::middleware('auth:sanctum')->group(function () {

    Route::get('users/{user}/cash-register', [UserCashRegisterController::class, 'show']);
    Route::post('users/{user}/cash-register', [UserCashRegisterController::class, 'assign']);
    Route::post('point-of-sales/{pointOfSale}/cash-registers', [UserCashRegisterController::class, 'store']);

});
Route::middleware('auth:sanctum')->group(function () {

    Route::post(
        '/contabilidad/accounting-setup',
        [AccountingConfigurationSeederController::class, 'initialize']
    );

});

Route::middleware('auth:sanctum')->group(function () {

    Route::post(
        '/inventario/productos/homologacion',
        [HomologacionProductoController::class, 'guardar']
    );

});

Route::middleware('auth:sanctum')
    ->prefix('facturacion')
    ->group(function () {

        Route::get(
            '/facturas',
            [FacturaController::class, 'listar']
        );

        Route::get(
            '/facturas/{id}',
            [FacturaController::class, 'obtener']
        );

        Route::post(
            '/facturas/{id}/anular',
            [FacturaController::class, 'anular']//pendiente
        );
    });
Route::middleware('auth:sanctum')->group(function () {

    Route::get(
        '/caja/card-ingresos-egresos',
        [CardIngresosEgresos::class, 'getCajaIngresosEgresos']
    );
});
/* Route::middleware('auth:sanctum')->prefix('facturacion/siat')->group(function () {

    Route::post('sincronizacion', [SiatSincronizacionController::class, 'sincronizar']);
    Route::get('sincronizacion/estado', [SiatSincronizacionController::class, 'estado']);

    Route::get('catalogos/{catalogo}', [SiatSincronizacionController::class, 'catalogo']);

    Route::get('parametros', [SiatSincronizacionController::class, 'parametros']);
    Route::get('parametros/{parametro}', [SiatSincronizacionController::class, 'parametro']);
});
 */

Route::middleware('auth:sanctum')->group(function () {

    Route::prefix('siat/catalogos')->group(function () {

        Route::post('/sincronizar', [SiatSincronizacionController::class, 'sincronizar']);

        // "estado" va ANTES de las rutas con comodín
        Route::get('/estado', [SiatSincronizacionController::class, 'estado']);

        Route::get('/catalogo/{catalogo}', [SiatSincronizacionController::class, 'catalogo']);

        Route::get('/parametro/{parametro}', [SiatSincronizacionController::class, 'parametro']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post(
        'facturacion/siat/emiti-factura',
        [FacturaEmitirController::class, 'emitirFactura']
    );
});

Route::middleware('auth:sanctum')->group(function () {

    // =========================================================
    // SIAT - CUIS
    // =========================================================

    // Listar CUIS de la empresa
    Route::get(
        '/facturacion/siat/cuis',
        [CuisController::class, 'index']
    );

    // Solicitar / renovar CUIS para un punto de venta
    Route::post(
        '/facturacion/siat/cuis/solicitar',
        [CuisController::class, 'solicitar']
    );


    // =========================================================
    // SIAT - CUFD
    // =========================================================

    // Listar CUFD de la empresa
    Route::get(
        '/facturacion/siat/cufd',
        [CufdController::class, 'index']
    );

    // Solicitar CUFD para un punto de venta
    Route::post(
        '/facturacion/siat/cufd/solicitar',
        [CufdController::class, 'solicitar']
    );
});



Route::middleware('auth:sanctum')->group(function () {


    /*
    |--------------------------------------------------------------------------
    | CAJAS
    |--------------------------------------------------------------------------*/


    Route::get(
        '/cash-registers',
        [CashRegisterController::class, 'index']
    );

    Route::post(
        '/cash-registers',
        [CashRegisterController::class, 'store']
    );

    Route::put(
        '/cash-registers/{id}',
        [CashRegisterController::class, 'update']
    );

    Route::patch(
        '/cash-registers/{id}/status',
        [CashRegisterController::class, 'toggleStatus']
    );

    /*
    |--------------------------------------------------------------------------
    | ASIGNACIONES
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/cash-register-assignments',
        [CashRegisterAssignmentController::class, 'index']
    );

    Route::post(
        '/cash-register-assignments',
        [CashRegisterAssignmentController::class, 'assign']
    );

    Route::delete(
        '/cash-register-assignments',
        [CashRegisterAssignmentController::class, 'remove']
    );
});


Route::middleware('auth:sanctum')->group(function () {
    Route::get(
        '/cash-registers/accounting-account-sales',
        [CashRegisterSessionController::class, 'getPlanCuentasVentas']
    );

    /*
    |--------------------------------------------------------------------------
    | MI CAJA
    |--------------------------------------------------------------------------
    */
    Route::get(
        '/cash-registers/{cashRegister}/summary',
        [CashRegisterSessionController::class, 'summary']
    );

    Route::get(
        '/my-cash-register',
        [CashRegisterSessionController::class, 'myCashRegister']
    );









    /*
    |--------------------------------------------------------------------------
    | SESIONES
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/cash-registers/{cashRegister}/current-session',
        [CashRegisterSessionController::class, 'currentSession']
    );

    Route::post(
        '/cash-registers/{cashRegister}/open-session',
        [CashRegisterSessionController::class, 'openSession']
    );

    Route::post(
        '/cash-registers/{cashRegister}/close-session',
        [CashRegisterSessionController::class, 'closeSession']
    );

    Route::get(
        '/cash-registers/{cashRegister}/sessions',
        [CashRegisterSessionController::class, 'index']
    );
});



Route::middleware('auth:sanctum')->group(function () {

    Route::prefix('siat-certificados')->group(function () {

        // ✅ RUTAS ESPECÍFICAS PRIMERO (con palabras clave)

        // Obtener certificado por empresa
        Route::get('/empresa/{idCompany}', [SiatCertificadoController::class, 'getByCompany'])
            ->name('siat-certificados.getByCompany');

        // Activar/Desactivar certificado
        Route::post('/{id}/toggle-active', [SiatCertificadoController::class, 'toggleActive'])
            ->name('siat-certificados.toggleActive');

        // Validar certificado
        Route::post('/{id}/validate', [SiatCertificadoController::class, 'validateCertificate'])
            ->name('siat-certificados.validate');

        // ✅ RUTAS GENÉRICAS DESPUÉS (con {id} variable)

        // Listar todos los certificados
        Route::get('/', [SiatCertificadoController::class, 'index'])
            ->name('siat-certificados.index');

        // Obtener certificado por ID
        Route::get('/{id}', [SiatCertificadoController::class, 'show'])
            ->name('siat-certificados.show');

        // Crear nuevo certificado
        Route::post('/', [SiatCertificadoController::class, 'store'])
            ->name('siat-certificados.store');

        // Actualizar certificado
        Route::post('/{id}', [SiatCertificadoController::class, 'update'])
            ->name('siat-certificados.update');

        // Eliminar certificado
        Route::delete('/{id}', [SiatCertificadoController::class, 'destroy'])
            ->name('siat-certificados.destroy');
    });
});
/*
|--------------------------------------------------------------------------
| CONFIGURACIÓN SIAT
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    Route::prefix('siat-configuraciones')->group(function () {

        Route::get(
            '/',
            [SiatConfiguracionController::class, 'index']
        );

        Route::get(
            '/company/{idCompany}',
            [SiatConfiguracionController::class, 'getByCompany']
        );

        Route::get(
            '/{id}',
            [SiatConfiguracionController::class, 'show']
        );

        Route::post(
            '/',
            [SiatConfiguracionController::class, 'store']
        );

        Route::put(
            '/{id}',
            [SiatConfiguracionController::class, 'update']
        );

        Route::delete(
            '/{id}',
            [SiatConfiguracionController::class, 'destroy']
        );

        Route::patch(
            '/{id}/toggle-active',
            [SiatConfiguracionController::class, 'toggleActive']
        );

        Route::post(
            '/{id}/validate-connection',
            [SiatConfiguracionController::class, 'validateConnection']
        );

        Route::patch(
            '/{id}/cuis',
            [SiatConfiguracionController::class, 'saveCuis']
        );

        Route::patch(
            '/{id}/cufd',
            [SiatConfiguracionController::class, 'saveCufd']
        );
    });
});




/*
|--------------------------------------------------------------------------
| SIAT
|--------------------------------------------------------------------------
*/

Route::post(
    '/siat/cuis',
    [SiatController::class, 'solicitarCUIS']
);

Route::post(
    '/siat/cufd',
    [SiatController::class, 'solicitarCUFD']
);



Route::middleware('auth:sanctum')->group(function () {

    Route::get(
        '/system-dashboard',
        [SystemDashboardController::class, 'index']
    );
});

Route::middleware('auth:sanctum')->group(function () {

    Route::get(
        '/dashboard',
        [DashboardController::class, 'index']
    );
});

Route::middleware('auth:sanctum')->group(function () {

    Route::prefix('reports/products')->group(function () {

        Route::get(
            '/',
            [
                ProductReportController::class,
                'index'
            ]
        );

        Route::get(
            '/excel',
            [
                ProductReportController::class,
                'excel'
            ]
        );

        Route::get(
            '/pdf',
            [
                ProductReportController::class,
                'pdf'
            ]
        );
    });
});

Route::middleware('auth:sanctum')->group(function () {

    Route::post(
        '/stock/import',
        [StockImportController::class, 'importStock']
    );
});


Route::middleware('auth:sanctum')
    ->prefix('accounting')
    ->group(function () {

        Route::get(
            '/account-mappings',
            [
                AccountingAccountMappingController::class,
                'index'
            ]
        );

        Route::post(
            '/account-mappings',
            [
                AccountingAccountMappingController::class,
                'store'
            ]
        );

        Route::get(
            '/account-mappings/{id}',
            [
                AccountingAccountMappingController::class,
                'show'
            ]
        );

        Route::put(
            '/account-mappings/{id}',
            [
                AccountingAccountMappingController::class,
                'update'
            ]
        );

        Route::patch(
            '/account-mappings/{id}/status',
            [
                AccountingAccountMappingController::class,
                'toggleStatus'
            ]
        );

        Route::delete(
            '/account-mappings/{id}',
            [
                AccountingAccountMappingController::class,
                'destroy'
            ]
        );
    });



Route::middleware('auth:sanctum')->prefix('accounting')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Tipos contables
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/account-types',
        [AccountingAccountController::class, 'types']
    );


    /*
    |--------------------------------------------------------------------------
    | Plan de cuentas
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/accounts',
        [AccountingAccountController::class, 'index']
    );

    Route::post(
        '/accounts',
        [AccountingAccountController::class, 'store']
    );

    Route::get(
        '/accounts/{id}',
        [AccountingAccountController::class, 'show']
    );

    Route::put(
        '/accounts/{id}',
        [AccountingAccountController::class, 'update']
    );

    Route::delete(
        '/accounts/{id}',
        [AccountingAccountController::class, 'destroy']
    );

    Route::patch(
        '/accounts/{id}/status',
        [AccountingAccountController::class, 'toggleStatus']
    );
});


Route::middleware('auth:sanctum')->prefix('accounting/account-types')->group(function () {

    Route::get('/', [
        AccountingAccountTypeController::class,
        'index'
    ]);

    Route::get('/{id}', [
        AccountingAccountTypeController::class,
        'show'
    ]);

    Route::post('/', [
        AccountingAccountTypeController::class,
        'store'
    ]);

    Route::put('/{id}', [
        AccountingAccountTypeController::class,
        'update'
    ]);

    Route::patch('/{id}/toggle-status', [
        AccountingAccountTypeController::class,
        'toggleStatus'
    ]);

    Route::delete('/{id}', [
        AccountingAccountTypeController::class,
        'destroy'
    ]);
});

Route::middleware('auth:sanctum')->group(function () {

    Route::get(
        '/access-user-modulos-json',
        [AccessModulesUserJsonController::class, 'index']
    );
});


Route::prefix('modules')->group(function () {

    Route::get('/', [ModuleController::class, 'index']);

    Route::post('/', [ModuleController::class, 'store']);

    Route::get('/{module}', [ModuleController::class, 'show']);

    Route::put('/{module}', [ModuleController::class, 'update']);

    Route::delete('/{module}', [ModuleController::class, 'destroy']);
});


Route::middleware('auth:sanctum')->group(function () {

    /**
     * Módulos disponibles
     */
    Route::get(
        '/accesses/modules/user',
        [AccessModulesUserController::class, 'modules']
    );


    /**
     * Obtener permisos del usuario
     */
    Route::get(
        '/accesses/modules/user/{userId}',
        [AccessModulesUserController::class, 'userModules']
    );


    /**
     * Guardar permisos del usuario
     */
    Route::put(
        '/accesses/modules/user/{userId}',
        [AccessModulesUserController::class, 'assignModules']
    );
});



Route::middleware('auth:sanctum')->group(function () {

    // Listar cotizaciones
    Route::get('/quotations', [QuotationController::class, 'index']);

    // Registrar cotización
    Route::post('/quotations', [QuotationController::class, 'store']);

    // Ver una cotización
    Route::get('/quotations/{id}', [QuotationController::class, 'show']);
});

Route::middleware('auth:sanctum')
    ->prefix('company')
    ->group(function () {

        Route::get('/', [
            CompanyUserController::class,
            'show'
        ]);

        Route::post('/', [
            CompanyUserController::class,
            'update'
        ]);

        Route::delete('/logo', [
            CompanyUserController::class,
            'deleteLogo'
        ]);
    });

Route::middleware('auth:sanctum')->group(function () {

    Route::get(
        '/ingresos-egresos-lista',
        [IncomeExpenseController::class, 'getIngresoEgresoLista']
    );

    /*
    |--------------------------------------------------------------------------
    | INGRESOS Y EGRESOS
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/income-expenses',
        [IncomeExpenseController::class, 'index']
    );

    Route::get(
        '/income-expenses/{id}',
        [IncomeExpenseController::class, 'show']
    );

    Route::post(
        '/income-expenses',
        [IncomeExpenseController::class, 'store']
    );
});


Route::middleware('auth:sanctum')->group(function () {

    Route::get(
        '/cash-categories',
        [CashCategoryController::class, 'index']
    );

    Route::post(
        '/cash-categories',
        [CashCategoryController::class, 'store']
    );

    Route::get(
        '/cash-categories/{id}',
        [CashCategoryController::class, 'show']
    );

    Route::put(
        '/cash-categories/{id}',
        [CashCategoryController::class, 'update']
    );

    Route::patch(
        '/cash-categories/{id}/status',
        [CashCategoryController::class, 'toggleStatus']
    );

    Route::delete(
        '/cash-categories/{id}',
        [CashCategoryController::class, 'destroy']
    );
});
Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | PERÍODOS CONTABLES
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/accounting-periods',
        [AccountingPeriodController::class, 'index']
    );

    Route::get(
        '/accounting-periods/current',
        [AccountingPeriodController::class, 'current']
    );

    Route::post(
        '/accounting-periods',
        [AccountingPeriodController::class, 'store']
    );

    Route::put(
        '/accounting-periods/{id}/close',
        [AccountingPeriodController::class, 'close']
    );

    Route::put(
        '/accounting-periods/{id}/reopen',
        [AccountingPeriodController::class, 'reopen']
    );

    Route::delete(
        '/accounting-periods/{id}',
        [AccountingPeriodController::class, 'destroy']
    );
});

Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | CONTABILIDAD
    |--------------------------------------------------------------------------
    */

    Route::prefix('accounting')->group(function () {

        /*
        |----------------------------------------------------------------------
        | LIBRO DIARIO
        |----------------------------------------------------------------------
        */

        Route::get(
            '/journal',
            [AccountingJournalController::class, 'index']
        );

        /*
        |----------------------------------------------------------------------
        | LIBRO MAYOR
        |----------------------------------------------------------------------
        */

        Route::get(
            '/ledger',
            [AccountingLedgerController::class, 'index']
        );
    });
});

use App\Domains\Contabilidad\Controllers\FinancialStatementController;

Route::middleware('auth:sanctum')
    ->prefix('accounting')
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | ESTADOS FINANCIEROS
        |--------------------------------------------------------------------------
        */

        // Estado de Resultados
        Route::get(
            '/financial-statements/income-statement',
            [FinancialStatementController::class, 'incomeStatement']
        );

        // Balance General
        Route::get(
            '/financial-statements/balance-sheet',
            [FinancialStatementController::class, 'balanceSheet']
        );
    });


Route::middleware('auth:sanctum')->group(function () {



    Route::get(
        '/accounting-entries',
        [AccountingEntryController::class, 'index']
    );

    Route::get(
        '/accounting-entries/{id}/summary',
        [AccountingEntryController::class, 'summary']
    );

    Route::get(
        '/accounting-entries/{id}',
        [AccountingEntryController::class, 'show']
    );

    Route::delete(
        '/accounting-entries/{id}',
        [AccountingEntryController::class, 'destroy']
    );
});


Route::middleware('auth:sanctum')->group(function () {

    Route::get(
        '/accounting-periods',
        [AccountingPeriodController::class, 'index']
    );

    Route::get(
        '/accounting-periods/current',
        [AccountingPeriodController::class, 'current']
    );

    Route::post(
        '/accounting-periods',
        [AccountingPeriodController::class, 'store']
    );

    Route::put(
        '/accounting-periods/{id}/close',
        [AccountingPeriodController::class, 'close']
    );

    Route::put(
        '/accounting-periods/{id}/reopen',
        [AccountingPeriodController::class, 'reopen']
    );

    Route::delete(
        '/accounting-periods/{id}',
        [AccountingPeriodController::class, 'destroy']
    );
});
/*
|--------------------------------------------------------------------------
| TRANSFERENCIAS DE PRODUCTOS
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | LISTAR TRANSFERENCIAS
    |--------------------------------------------------------------------------
    |
    | GET /api/product-transfers
    |
    | Parámetros opcionales:
    |
    | ?type=sent
    | ?type=received
    | ?status=PENDIENTE
    | ?search=TRA-000001
    | ?origin_branch_id=1
    | ?destination_branch_id=2
    | ?date_from=2026-09-01
    | ?date_to=2026-09-05
    | ?per_page=10
    |
    */

    Route::get(
        '/product-transfers',
        [ProductTransferController::class, 'index']
    );


    /*
    |--------------------------------------------------------------------------
    | CREAR TRANSFERENCIA
    |--------------------------------------------------------------------------
    |
    | POST /api/product-transfers
    |
    */

    Route::post(
        '/product-transfers',
        [ProductTransferController::class, 'store']
    );


    /*
    |--------------------------------------------------------------------------
    | MOSTRAR TRANSFERENCIA
    |--------------------------------------------------------------------------
    |
    | GET /api/product-transfers/{id}
    |
    */

    Route::get(
        '/product-transfers/{id}',
        [ProductTransferController::class, 'show']
    );


    /*
    |--------------------------------------------------------------------------
    | ACEPTAR TRANSFERENCIA
    |--------------------------------------------------------------------------
    |
    | POST /api/product-transfers/{id}/accept
    |
    */

    Route::post(
        '/product-transfers/{id}/accept',
        [ProductTransferController::class, 'accept']
    );


    /*
    |--------------------------------------------------------------------------
    | RECHAZAR TRANSFERENCIA
    |--------------------------------------------------------------------------
    |
    | POST /api/product-transfers/{id}/reject
    |
    */

    Route::post(
        '/product-transfers/{id}/reject',
        [ProductTransferController::class, 'reject']
    );


    /*
    |--------------------------------------------------------------------------
    | CANCELAR TRANSFERENCIA
    |--------------------------------------------------------------------------
    |
    | POST /api/product-transfers/{id}/cancel
    |
    */

    Route::post(
        '/product-transfers/{id}/cancel',
        [ProductTransferController::class, 'cancel']
    );
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user/context', [UserContextController::class, 'index']);
});
/*
|--------------------------------------------------------------------------
| PRODUCT TRANSFERS
|--------------------------------------------------------------------------
*/



Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | DEVOLUCIONES
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/sale-returns',
        [SaleReturnController::class, 'index']
    );

    Route::get(
        '/sale-returns/{id}',
        [SaleReturnController::class, 'show']
    );

    Route::post(
        '/sales/{saleId}/returns',
        [SaleReturnController::class, 'store']
    );

    Route::get(
        '/sales/{saleId}/returns',
        [SaleReturnController::class, 'bySale']
    );

    Route::get(
        '/sales/{saleId}/returns/available-items',
        [SaleReturnController::class, 'availableItems']
    );
});


Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('companies')->group(function () {
        Route::get('/company-user', [
            CompanyController::class,
            'getCompanyUser'
        ]);

        Route::get('/', [
            CompanyController::class,
            'index'
        ]);

        Route::get('/{company}', [
            CompanyController::class,
            'show'
        ]);

        Route::post('/', [
            CompanyController::class,
            'store'
        ]);

        Route::put('/{company}', [
            CompanyController::class,
            'update'
        ]);

        Route::delete('/{company}', [
            CompanyController::class,
            'destroy'
        ]);
    });
});





Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | ACCESOS
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/accesses',
        [AccessController::class, 'index']
    );

    Route::get(
        '/accesses/modules',
        [AccessController::class, 'modules']
    );

    Route::put(
        '/accesses/user/{userId}',
        [AccessController::class, 'update']
    );
});
Route::middleware('auth:sanctum')
    ->prefix('point-of-sales')
    ->group(function () {

        Route::get('/branch/{branchId}', [
            PointOfSaleController::class,
            'getByBranch'
        ]);

        Route::get('/{id}', [
            PointOfSaleController::class,
            'show'
        ]);

        Route::post('/', [
            PointOfSaleController::class,
            'store'
        ]);

        Route::put('/{id}', [
            PointOfSaleController::class,
            'update'
        ]);

        Route::delete('/{id}', [
            PointOfSaleController::class,
            'destroy'
        ]);
    });
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/sales', [
        SaleController::class,
        'store'
    ]);
    Route::get('/sales', [SaleController::class, 'index']);
});


Route::middleware('auth:sanctum')->group(function () {

    Route::put('/stock/update', [StockProductController::class, 'updateStock']);
});

Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | MOVIMIENTOS DE STOCK
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/stock-movements',
        [StockMovementController::class, 'index']
    );

    Route::get(
        '/stock-movements/{id}',
        [StockMovementController::class, 'show']
    );
});

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/clients', [ClientController::class, 'index']);

    Route::post('/clients', [ClientController::class, 'store']);

    Route::get('/clients/{id}', [ClientController::class, 'show']);

    Route::put('/clients/{id}', [ClientController::class, 'update']);

    Route::delete('/clients/{id}', [ClientController::class, 'destroy']);

    Route::post(
        '/clients/{id}/restore',
        [ClientController::class, 'restore']
    );
});

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/units', [
        UnitController::class,
        'index'
    ]);

    Route::post('/units', [
        UnitController::class,
        'store'
    ]);

    Route::get('/units/{id}', [
        UnitController::class,
        'show'
    ]);

    Route::put('/units/{id}', [
        UnitController::class,
        'update'
    ]);

    Route::delete('/units/{id}', [
        UnitController::class,
        'destroy'
    ]);
});

Route::middleware('auth:sanctum')->group(function () {

    Route::apiResource(
        'type-data',
        TypeDataController::class
    );
});

//========== PRIMERO VEZ CUANO SE CREA LA EMPRESA, USUARIO, ACCESOS =========
Route::post('/setup', [
    SetupController::class,
    'store'
]);
//===========================================================================
//====================== SEGURIDAD ====================================================

Route::get(
    '/roles',
    [RolesController::class, 'index']
);

//Route::middleware('auth:sanctum')->group(function () {

//Route::apiResource('modules', ModuleController::class);

//});

Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);
//=======================================================================================





//===================== INVENTARIO =================================================
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('brands', BrandController::class); //MARCA


    Route::apiResource('suppliers', SupplierController::class);
    Route::apiResource('categories', CategoryController::class);


    Route::apiResource('products', ProductController::class);
    // PRODUCTOS PARA VENTAS
    Route::get('products-sale', [ProductController::class, 'index_sale']);

    // INVENTARIO POR SUCURSAL
    Route::get(
        'inventory/stock-by-branch',
        [ProductController::class, 'stockByBranch']
    );
});
//=================================================================================





//========================= EMPRESA ===============================================
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('branches', BranchController::class);

    Route::get('branche-point-of-sale', [BranchController::class, 'getBranchPointOfSale']);



    Route::get('/branches-branch-user-access', [BranchController::class, 'getBranchUserAccess']);
    Route::get(
        '/users',
        [UserController::class, 'index']
    );
    Route::post(
        '/users',
        [UserController::class, 'store']
    );
    Route::put(
        '/users/{id}',
        [UserController::class, 'update']
    );
});
//=================================================================================
