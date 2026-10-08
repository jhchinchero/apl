<?php

namespace App\Domains\Contabilidad\Services\Seeder;



use Illuminate\Support\Facades\DB;

use App\Domains\Contabilidad\Models\AccountingAccountType;
use App\Domains\Contabilidad\Models\AccountingAccount;
use App\Domains\Contabilidad\Models\AccountingAccountMapping;
use App\Domains\Contabilidad\Models\AccountingPaymentMapping;

use App\Domains\Caja\Models\CashRegister;

class AccountingSetupService
{
    /**
     * Configuración contable completa de una empresa.
     *
     * Crea:
     * 1. Tipos de cuenta
     * 2. Plan de cuentas
     * 3. Mapeos de operaciones
     * 4. Mapeos de métodos de pago
     * 5. Configuración de cajas
     */
    public function setup(string $companyId): array
{
    return DB::transaction(function () use ($companyId) {

        /*
        |--------------------------------------------------------------------------
        | Verificar si ya existe un plan contable
        |--------------------------------------------------------------------------
        */

        $existingAccounts = AccountingAccount::where(
            'id_company',
            $companyId
        )->count();

        if ($existingAccounts > 0) {

            return [
                'success' => false,
                'already_initialized' => true,
                'message' => 'La empresa ya tiene un plan contable configurado.',
                'accounts' => $existingAccounts,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | 1. TIPOS
        |--------------------------------------------------------------------------
        */

        $typeIds = $this->createAccountTypes();

        /*
        |--------------------------------------------------------------------------
        | 2. PLAN DE CUENTAS
        |--------------------------------------------------------------------------
        */

        $accounts = $this->createChartOfAccounts(
            $companyId,
            $typeIds
        );

        /*
        |--------------------------------------------------------------------------
        | 3. MAPEOS
        |--------------------------------------------------------------------------
        */

        $this->createOperationMappings(
            $companyId,
            $accounts
        );

        /*
        |--------------------------------------------------------------------------
        | 4. MÉTODOS DE PAGO
        |--------------------------------------------------------------------------
        */

        $this->createPaymentMappings(
            $companyId,
            $accounts
        );

        /*
        |--------------------------------------------------------------------------
        | 5. CAJAS
        |--------------------------------------------------------------------------
        */

        $this->configureCashRegisters(
            $companyId,
            $accounts['cajaPrincipal']
        );

        return [
            'success' => true,
            'already_initialized' => false,
            'message' => 'Configuración contable creada correctamente.',
            'accounts' => AccountingAccount::where(
                'id_company',
                $companyId
            )->count(),

            'operation_mappings' =>
                AccountingAccountMapping::where(
                    'id_company',
                    $companyId
                )->count(),

            'payment_mappings' =>
                AccountingPaymentMapping::where(
                    'id_company',
                    $companyId
                )->count(),

            'cash_registers' =>
                CashRegister::where(
                    'id_company',
                    $companyId
                )->count(),
        ];
    });
}

    /*
    |--------------------------------------------------------------------------
    | TIPOS DE CUENTA
    |--------------------------------------------------------------------------
    */

    private function createAccountTypes(): array
    {
        $types = [

            [
                'code' => 'ACT',
                'name' => 'ACTIVO',
                'nature' => 'DEUDORA',
                'description' => 'Bienes y derechos de la empresa.',
            ],

            [
                'code' => 'PAS',
                'name' => 'PASIVO',
                'nature' => 'ACREEDORA',
                'description' => 'Obligaciones y deudas de la empresa.',
            ],

            [
                'code' => 'PAT',
                'name' => 'PATRIMONIO',
                'nature' => 'ACREEDORA',
                'description' => 'Patrimonio y capital de la empresa.',
            ],

            [
                'code' => 'ING',
                'name' => 'INGRESO',
                'nature' => 'ACREEDORA',
                'description' => 'Ingresos generados por la empresa.',
            ],

            [
                'code' => 'COS',
                'name' => 'COSTO',
                'nature' => 'DEUDORA',
                'description' => 'Costos relacionados con la actividad comercial.',
            ],

            [
                'code' => 'GAS',
                'name' => 'GASTO',
                'nature' => 'DEUDORA',
                'description' => 'Gastos administrativos y operativos.',
            ],
        ];

        $typeIds = [];

        foreach ($types as $type) {

            $record = AccountingAccountType::updateOrCreate(
                [
                    'code' => $type['code'],
                ],
                [
                    'name' => $type['name'],
                    'nature' => $type['nature'],
                    'status' => true,
                    'description' => $type['description'],
                ]
            );

            $typeIds[$type['code']] = $record->id;
        }

        return $typeIds;
    }

    /*
    |--------------------------------------------------------------------------
    | PLAN DE CUENTAS
    |--------------------------------------------------------------------------
    */

    private function createChartOfAccounts(
        string $companyId,
        array $typeIds
    ): array {

        /*
        |--------------------------------------------------------------------------
        | ACTIVOS
        |--------------------------------------------------------------------------
        */

        $rootActivo = $this->account(
            $companyId,
            '1',
            'ACTIVO',
            'ACT',
            null,
            false,
            1,
            $typeIds
        );

        $activoCorriente = $this->account(
            $companyId,
            '1.1',
            'ACTIVO CORRIENTE',
            'ACT',
            $rootActivo->id,
            false,
            2,
            $typeIds
        );

        /*
        |--------------------------------------------------------------------------
        | PASIVOS
        |--------------------------------------------------------------------------
        */

        $rootPasivo = $this->account(
            $companyId,
            '2',
            'PASIVO',
            'PAS',
            null,
            false,
            1,
            $typeIds
        );

        $pasivoCorriente = $this->account(
            $companyId,
            '2.1',
            'PASIVO CORRIENTE',
            'PAS',
            $rootPasivo->id,
            false,
            2,
            $typeIds
        );

        /*
        |--------------------------------------------------------------------------
        | PATRIMONIO
        |--------------------------------------------------------------------------
        */

        $rootPatrimonio = $this->account(
            $companyId,
            '3',
            'PATRIMONIO',
            'PAT',
            null,
            false,
            1,
            $typeIds
        );

        $patrimonio = $this->account(
            $companyId,
            '3.1',
            'PATRIMONIO',
            'PAT',
            $rootPatrimonio->id,
            false,
            2,
            $typeIds
        );

        /*
        |--------------------------------------------------------------------------
        | INGRESOS
        |--------------------------------------------------------------------------
        */

        $rootIngresos = $this->account(
            $companyId,
            '4',
            'INGRESOS',
            'ING',
            null,
            false,
            1,
            $typeIds
        );

        $ingresosOperativos = $this->account(
            $companyId,
            '4.1',
            'INGRESOS OPERATIVOS',
            'ING',
            $rootIngresos->id,
            false,
            2,
            $typeIds
        );

        /*
        |--------------------------------------------------------------------------
        | COSTOS
        |--------------------------------------------------------------------------
        */

        $rootCostos = $this->account(
            $companyId,
            '5',
            'COSTOS',
            'COS',
            null,
            false,
            1,
            $typeIds
        );

        $costosVenta = $this->account(
            $companyId,
            '5.1',
            'COSTO DE VENTAS',
            'COS',
            $rootCostos->id,
            false,
            2,
            $typeIds
        );

        /*
        |--------------------------------------------------------------------------
        | GASTOS
        |--------------------------------------------------------------------------
        */

        $rootGastos = $this->account(
            $companyId,
            '6',
            'GASTOS',
            'GAS',
            null,
            false,
            1,
            $typeIds
        );

        $gastosOperativos = $this->account(
            $companyId,
            '6.1',
            'GASTOS OPERATIVOS',
            'GAS',
            $rootGastos->id,
            false,
            2,
            $typeIds
        );

        /*
        |--------------------------------------------------------------------------
        | CUENTAS DE MOVIMIENTO
        |--------------------------------------------------------------------------
        */

        $cajaPrincipal = $this->account(
            $companyId,
            '1.1.01',
            'Caja Principal',
            'ACT',
            $activoCorriente->id,
            true,
            3,
            $typeIds
        );

        $bancos = $this->account(
            $companyId,
            '1.1.02',
            'Bancos',
            'ACT',
            $activoCorriente->id,
            true,
            3,
            $typeIds
        );

        $cuentasPorCobrar = $this->account(
            $companyId,
            '1.1.03',
            'Cuentas por Cobrar Clientes',
            'ACT',
            $activoCorriente->id,
            true,
            3,
            $typeIds
        );

        $inventarios = $this->account(
            $companyId,
            '1.1.04',
            'Inventarios',
            'ACT',
            $activoCorriente->id,
            true,
            3,
            $typeIds
        );

        $cuentasPorPagar = $this->account(
            $companyId,
            '2.1.01',
            'Cuentas por Pagar Proveedores',
            'PAS',
            $pasivoCorriente->id,
            true,
            3,
            $typeIds
        );

        $capital = $this->account(
            $companyId,
            '3.1.01',
            'Capital',
            'PAT',
            $patrimonio->id,
            true,
            3,
            $typeIds
        );

        $ventas = $this->account(
            $companyId,
            '4.1.01',
            'Ventas',
            'ING',
            $ingresosOperativos->id,
            true,
            3,
            $typeIds
        );

        $devolucionesVentas = $this->account(
            $companyId,
            '4.1.02',
            'Devoluciones sobre Ventas',
            'ING',
            $ingresosOperativos->id,
            true,
            3,
            $typeIds
        );

        $costoVenta = $this->account(
            $companyId,
            '5.1.01',
            'Costo de Ventas',
            'COS',
            $costosVenta->id,
            true,
            3,
            $typeIds
        );

        $gastos = $this->account(
            $companyId,
            '6.1.01',
            'Gastos Operativos',
            'GAS',
            $gastosOperativos->id,
            true,
            3,
            $typeIds
        );

        return [
            'cajaPrincipal' => $cajaPrincipal,
            'bancos' => $bancos,
            'cuentasPorCobrar' => $cuentasPorCobrar,
            'inventarios' => $inventarios,
            'cuentasPorPagar' => $cuentasPorPagar,
            'capital' => $capital,
            'ventas' => $ventas,
            'devolucionesVentas' => $devolucionesVentas,
            'costoVenta' => $costoVenta,
            'gastos' => $gastos,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | CREAR / ACTUALIZAR CUENTA
    |--------------------------------------------------------------------------
    */

    private function account(
        string $companyId,
        string $code,
        string $name,
        string $typeCode,
        ?int $parentId,
        bool $allowsEntries,
        int $level,
        array $typeIds
    ): AccountingAccount {

        return AccountingAccount::updateOrCreate(
            [
                'id_company' => $companyId,
                'code' => $code,
            ],
            [
                'account_type_id' => $typeIds[$typeCode],
                'parent_id' => $parentId,
                'name' => $name,
                'level' => $level,
                'allows_entries' => $allowsEntries,
                'status' => true,
                'description' => null,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | MAPEOS DE OPERACIONES
    |--------------------------------------------------------------------------
    */

    private function createOperationMappings(
        string $companyId,
        array $accounts
    ): void {

        $mappings = [

            [
                'key' => 'VENTA',
                'name' => 'Venta',
                'accounting_account_id' => $accounts['ventas']->id,
                'description' => 'Cuenta utilizada para registrar ventas.',
            ],

            [
                'key' => 'CUENTA_POR_COBRAR',
                'name' => 'Cuenta por cobrar',
                'accounting_account_id' => $accounts['cuentasPorCobrar']->id,
                'description' => 'Cuenta utilizada para ventas a crédito.',
            ],

            [
                'key' => 'INVENTARIO',
                'name' => 'Inventario',
                'accounting_account_id' => $accounts['inventarios']->id,
                'description' => 'Cuenta utilizada para inventarios.',
            ],

            [
                'key' => 'COSTO_VENTA',
                'name' => 'Costo de venta',
                'accounting_account_id' => $accounts['costoVenta']->id,
                'description' => 'Cuenta utilizada para registrar costo de ventas.',
            ],

            [
                'key' => 'DEVOLUCION_VENTAS',
                'name' => 'Devolución de ventas',
                'accounting_account_id' => $accounts['devolucionesVentas']->id,
                'description' => 'Cuenta utilizada para devoluciones sobre ventas.',
            ],

            [
                'key' => 'DEVOLUCION_EFECTIVO',
                'name' => 'Devolución de efectivo',
                'accounting_account_id' => $accounts['cajaPrincipal']->id,
                'description' => 'Cuenta utilizada para devoluciones en efectivo.',
            ],

            [
                'key' => 'CAJA_APERTURA',
                'name' => 'Apertura de caja',
                'accounting_account_id' => $accounts['cajaPrincipal']->id,
                'description' => 'Cuenta utilizada para apertura de caja.',
            ],

            [
                'key' => 'CAJA_CIERRE',
                'name' => 'Cierre de caja',
                'accounting_account_id' => $accounts['cajaPrincipal']->id,
                'description' => 'Cuenta utilizada para cierre de caja.',
            ],

            [
                'key' => 'INGRESO_CAJA',
                'name' => 'Ingreso de caja',
                'accounting_account_id' => $accounts['cajaPrincipal']->id,
                'description' => 'Cuenta utilizada para ingresos manuales de caja.',
            ],

            [
                'key' => 'EGRESO_CAJA',
                'name' => 'Egreso de caja',
                'accounting_account_id' => $accounts['cajaPrincipal']->id,
                'description' => 'Cuenta utilizada para egresos manuales de caja.',
            ],

            [
                'key' => 'GASTO',
                'name' => 'Gasto',
                'accounting_account_id' => $accounts['gastos']->id,
                'description' => 'Cuenta utilizada para registrar gastos.',
            ],

            [
                'key' => 'PAGO_CUENTA_POR_COBRAR',
                'name' => 'Pago cuenta por cobrar',
                'accounting_account_id' => $accounts['cuentasPorCobrar']->id,
                'description' => 'Cuenta utilizada para cobros de clientes.',
            ],
        ];

        foreach ($mappings as $mapping) {

            AccountingAccountMapping::updateOrCreate(
                [
                    'id_company' => $companyId,
                    'key' => $mapping['key'],
                ],
                [
                    'name' => $mapping['name'],
                    'accounting_account_id' => $mapping['accounting_account_id'],
                    'description' => $mapping['description'],
                    'status' => true,
                ]
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | MAPEOS DE MÉTODOS DE PAGO
    |--------------------------------------------------------------------------
    */

    private function createPaymentMappings(
        string $companyId,
        array $accounts
    ): void {

        $mappings = [

            [
                'payment_method' => 'CARD',
                'accounting_account_id' => $accounts['bancos']->id,
            ],

            [
                'payment_method' => 'TRANSFER',
                'accounting_account_id' => $accounts['bancos']->id,
            ],

            [
                'payment_method' => 'QR',
                'accounting_account_id' => $accounts['bancos']->id,
            ],

            [
                'payment_method' => 'CHECK',
                'accounting_account_id' => $accounts['bancos']->id,
            ],

            [
                'payment_method' => 'OTHER',
                'accounting_account_id' => $accounts['bancos']->id,
            ],
        ];

        foreach ($mappings as $mapping) {

            AccountingPaymentMapping::updateOrCreate(
                [
                    'id_company' => $companyId,
                    'payment_method' => $mapping['payment_method'],
                ],
                [
                    'accounting_account_id' => $mapping['accounting_account_id'],
                    'status' => true,
                ]
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CONFIGURAR CAJAS
    |--------------------------------------------------------------------------
    */

    private function configureCashRegisters(
        string $companyId,
        AccountingAccount $cajaPrincipal
    ): void {

        /*
        |--------------------------------------------------------------------------
        | Buscar cajas de la empresa
        |--------------------------------------------------------------------------
        */

        $cashRegisters = CashRegister::where(
            'id_company',
            $companyId
        )
            ->orderBy('id')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Si no existen cajas, no hacemos nada.
        |--------------------------------------------------------------------------
        */

        if ($cashRegisters->isEmpty()) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Contador para nuevas cuentas de caja
        |--------------------------------------------------------------------------
        */

        $cashNumber = 0;

        foreach ($cashRegisters as $cashRegister) {

            /*
            |--------------------------------------------------------------------------
            | Si ya tiene cuenta contable, no modificarla.
            |--------------------------------------------------------------------------
            */

            if (!empty($cashRegister->accounting_account_id)) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Primera caja
            |--------------------------------------------------------------------------
            */

            if ($cashNumber === 0) {

                $cashRegister->accounting_account_id = $cajaPrincipal->id;

                $cashRegister->save();

                $cashNumber++;

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Cajas adicionales
            |
            | 1.1.05
            | 1.1.06
            | 1.1.07
            | ...
            |--------------------------------------------------------------------------
            */

            $cashNumber++;

            $code = '1.1.' . str_pad(
                4 + $cashNumber,
                2,
                '0',
                STR_PAD_LEFT
            );

            $account = AccountingAccount::firstOrCreate(
                [
                    'id_company' => $companyId,
                    'code' => $code,
                ],
                [
                    'account_type_id' => $cajaPrincipal->account_type_id,
                    'parent_id' => $cajaPrincipal->parent_id,
                    'name' => $cashRegister->name,
                    'level' => 3,
                    'allows_entries' => true,
                    'status' => true,
                    'description' => 'Cuenta contable asociada a la caja ' . $cashRegister->name,
                ]
            );

            $cashRegister->accounting_account_id = $account->id;

            $cashRegister->save();
        }
    }
}