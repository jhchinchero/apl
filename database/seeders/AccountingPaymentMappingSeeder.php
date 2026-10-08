<?php

namespace Database\Seeders;


use Illuminate\Database\Seeder;
use App\Domains\Contabilidad\Models\AccountingAccount;
use App\Domains\Contabilidad\Models\AccountingPaymentMapping;

class AccountingPaymentMappingSeeder extends Seeder
{
    public function run(): void
    {
        $idCompany = 'EMP-00001';

        /*
        |--------------------------------------------------------------------------
        | Buscar bancos
        |--------------------------------------------------------------------------
        */

        $bancoBisa = AccountingAccount::query()
            ->where('id_company', $idCompany)
            ->where('code', '1.01.02.01')
            ->first();

        $bancoUnion = AccountingAccount::query()
            ->where('id_company', $idCompany)
            ->where('code', '1.01.02.02')
            ->first();

        if (!$bancoBisa) {

            throw new \RuntimeException(
                "No existe la cuenta 1.01.02.01 BANCO BISA."
            );
        }

        if (!$bancoUnion) {

            throw new \RuntimeException(
                "No existe la cuenta 1.01.02.02 BANCO UNION."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Métodos de pago
        |--------------------------------------------------------------------------
        |
        | CASH NO SE CONFIGURA AQUÍ.
        |
        | CASH obtiene su cuenta desde:
        |
        | cash_registers.accounting_account_id
        |
        */

        $mappings = [

            [
                'payment_method' => 'CARD',
                'account' => $bancoBisa,
            ],

            [
                'payment_method' => 'TRANSFER',
                'account' => $bancoBisa,
            ],

            [
                'payment_method' => 'QR',
                'account' => $bancoBisa,
            ],

            [
                'payment_method' => 'CHECK',
                'account' => $bancoUnion,
            ],

            [
                'payment_method' => 'OTHER',
                'account' => $bancoBisa,
            ],
        ];

        /*
        |--------------------------------------------------------------------------
        | Crear / actualizar
        |--------------------------------------------------------------------------
        */

        foreach ($mappings as $mapping) {

            AccountingPaymentMapping::updateOrCreate(
                [
                    'id_company' => $idCompany,
                    'payment_method' =>
                        $mapping['payment_method'],
                ],
                [
                    'accounting_account_id' =>
                        $mapping['account']->id,

                    'status' => true,
                ]
            );
        }

        $this->command?->info(
            "Mapeos de métodos de pago creados correctamente para {$idCompany}."
        );
    }
}

