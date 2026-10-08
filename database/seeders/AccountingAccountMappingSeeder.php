<?php

namespace Database\Seeders;




use Illuminate\Database\Seeder;
use App\Domains\Contabilidad\Models\AccountingAccount;
use App\Domains\Contabilidad\Models\AccountingAccountMapping;

class AccountingAccountMappingSeeder extends Seeder
{
    public function run(): void
    {
        $idCompany = 'EMP-00001';

        /*
        |--------------------------------------------------------------------------
        | Obtener cuentas contables
        |--------------------------------------------------------------------------
        */

        $accounts = AccountingAccount::query()
            ->where('id_company', $idCompany)
            ->whereIn('code', [
                '1.01.01.01', // Caja Principal
                '1.01.02.01', // Banco BISA
                '1.01.02.02', // Banco UNION
                '1.01.03',    // Cuentas por Cobrar
                '1.01.04.01', // Inventario Productos
                '4.01',       // Ventas
                '5.01',       // Costo de Ventas
                '6.01.01',    // Sueldos
                '6.01.02',    // Servicios
            ])
            ->get()
            ->keyBy('code');

        /*
        |--------------------------------------------------------------------------
        | Validar cuentas necesarias
        |--------------------------------------------------------------------------
        */

        $requiredAccounts = [
            '1.01.01.01',
            '1.01.03',
            '1.01.04.01',
            '4.01',
            '5.01',
        ];

        foreach ($requiredAccounts as $code) {

            if (!isset($accounts[$code])) {

                throw new \RuntimeException(
                    "No existe la cuenta contable {$code} " .
                    "para la empresa {$idCompany}. " .
                    "Ejecuta primero AccountingAccountSeeder."
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Mapeos de operaciones
        |--------------------------------------------------------------------------
        */

        $mappings = [

            /*
            |------------------------------------------------------------------
            | VENTA
            |------------------------------------------------------------------
            */

            [
                'key' => 'VENTA',
                'name' => 'Ventas',
                'account_code' => '4.01',
                'description' =>
                    'Cuenta utilizada para registrar las ventas.',
            ],

            /*
            |------------------------------------------------------------------
            | CUENTAS POR COBRAR
            |------------------------------------------------------------------
            */

            [
                'key' => 'CUENTA_POR_COBRAR',
                'name' => 'Cuentas por Cobrar',
                'account_code' => '1.01.03',
                'description' =>
                    'Cuenta utilizada para ventas a crédito y saldos de clientes.',
            ],

            /*
            |------------------------------------------------------------------
            | INVENTARIO
            |------------------------------------------------------------------
            */

            [
                'key' => 'INVENTARIO',
                'name' => 'Inventario de Productos',
                'account_code' => '1.01.04.01',
                'description' =>
                    'Cuenta utilizada para controlar el inventario de productos.',
            ],

            /*
            |------------------------------------------------------------------
            | COSTO DE VENTA
            |------------------------------------------------------------------
            */

            [
                'key' => 'COSTO_VENTA',
                'name' => 'Costo de Ventas',
                'account_code' => '5.01',
                'description' =>
                    'Cuenta utilizada para registrar el costo de los productos vendidos.',
            ],

            /*
            |------------------------------------------------------------------
            | DEVOLUCION DE VENTA
            |------------------------------------------------------------------
            */

            [
                'key' => 'DEVOLUCION_VENTA',
                'name' => 'Devolución de Ventas',
                'account_code' => '4.01',
                'description' =>
                    'Cuenta utilizada temporalmente para devoluciones de ventas.',
            ],

            /*
            |------------------------------------------------------------------
            | DEVOLUCION DE EFECTIVO
            |------------------------------------------------------------------
            */

            [
                'key' => 'DEVOLUCION_EFECTIVO',
                'name' => 'Devolución de Efectivo',
                'account_code' => '1.01.01.01',
                'description' =>
                    'Cuenta utilizada para devoluciones de efectivo.',
            ],

            /*
            |------------------------------------------------------------------
            | APERTURA DE CAJA
            |------------------------------------------------------------------
            */

            [
                'key' => 'CAJA_APERTURA',
                'name' => 'Apertura de Caja',
                'account_code' => '1.01.01.01',
                'description' =>
                    'Cuenta utilizada para operaciones de apertura de caja.',
            ],

            /*
            |------------------------------------------------------------------
            | CIERRE DE CAJA
            |------------------------------------------------------------------
            */

            [
                'key' => 'CAJA_CIERRE',
                'name' => 'Cierre de Caja',
                'account_code' => '1.01.01.01',
                'description' =>
                    'Cuenta utilizada para operaciones de cierre de caja.',
            ],

            /*
            |------------------------------------------------------------------
            | INGRESO DE CAJA
            |------------------------------------------------------------------
            */

            [
                'key' => 'INGRESO_CAJA',
                'name' => 'Ingreso de Caja',
                'account_code' => '1.01.01.01',
                'description' =>
                    'Cuenta utilizada para ingresos manuales de caja.',
            ],

            /*
            |------------------------------------------------------------------
            | EGRESO DE CAJA
            |------------------------------------------------------------------
            */

            [
                'key' => 'EGRESO_CAJA',
                'name' => 'Egreso de Caja',
                'account_code' => '1.01.01.01',
                'description' =>
                    'Cuenta utilizada para egresos manuales de caja.',
            ],

            /*
            |------------------------------------------------------------------
            | GASTOS
            |------------------------------------------------------------------
            */

            [
                'key' => 'GASTO',
                'name' => 'Gastos',
                'account_code' => '6.01.01',
                'description' =>
                    'Cuenta utilizada para gastos administrativos.',
            ],

            /*
            |------------------------------------------------------------------
            | PAGO CUENTAS POR COBRAR
            |------------------------------------------------------------------
            */

            [
                'key' => 'PAGO_CUENTA_POR_COBRAR',
                'name' => 'Pago de Cuentas por Cobrar',
                'account_code' => '1.01.03',
                'description' =>
                    'Cuenta utilizada para registrar pagos de clientes.',
            ],
        ];

        /*
        |--------------------------------------------------------------------------
        | Crear / actualizar mapeos
        |--------------------------------------------------------------------------
        */

        foreach ($mappings as $mapping) {

            if (!isset($accounts[$mapping['account_code']])) {

                throw new \RuntimeException(
                    "La cuenta {$mapping['account_code']} " .
                    "no existe para la empresa {$idCompany}."
                );
            }

            AccountingAccountMapping::updateOrCreate(
                [
                    'id_company' => $idCompany,
                    'key' => $mapping['key'],
                ],
                [
                    'name' => $mapping['name'],
                    'accounting_account_id' =>
                        $accounts[$mapping['account_code']]->id,
                    'description' => $mapping['description'],
                    'status' => true,
                ]
            );
        }

        $this->command?->info(
            "Mapeos contables creados correctamente para {$idCompany}."
        );
    }
}
