<?php

namespace Database\Seeders;



use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

use App\Domains\Contabilidad\Models\AccountingAccountType;
use App\Domains\Contabilidad\Models\AccountingAccount;
use App\Domains\Contabilidad\Models\AccountingAccountMapping;
use App\Domains\Contabilidad\Models\AccountingPaymentMapping;
use App\Domains\Contabilidad\Services\Seeder\AccountingSetupService;

use App\Domains\Caja\Models\CashRegister;

class AccountingAccountSeeder extends Seeder
{
   public function run(): void
    {
        $service = app(AccountingSetupService::class);

        $result = $service->setup('EMP-00001');

        $this->command->info($result['message']);

        $this->command->info(
            'Cuentas: ' . $result['accounts']
        );

        $this->command->info(
            'Mapeos operaciones: ' . $result['operation_mappings']
        );

        $this->command->info(
            'Mapeos pagos: ' . $result['payment_mappings']
        );

        $this->command->info(
            'Cajas: ' . $result['cash_registers']
        );
    }
}
