<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CashCategorySeeder extends Seeder
{
    public function run(): void
    {
        $companies = DB::table('companies')->select('id_company')->get();
        $categories = [
            ['code' => 'VENTA_EFECTIVO', 'name' => 'VENTA EFECTIVO', 'type' => 'INGRESO', 'status' => true,],
            ['code' => 'APORTE_CAJA', 'name' => 'APORTE DE CAJA', 'type' => 'INGRESO', 'status' => true,],
            ['code' => 'OTROS_INGRESOS', 'name' => 'OTROS INGRESOS', 'type' => 'INGRESO', 'status' => true,],
            /* |-------------------------------------------------------------------------- | EGRESOS |-------------------------------------------------------------------------- */
            ['code' => 'RETIRO_EFECTIVO', 'name' => 'RETIRO DE EFECTIVO', 'type' => 'EGRESO', 'status' => true,],
            ['code' => 'GASTOS', 'name' => 'GASTOS', 'type' => 'EGRESO', 'status' => true,],
            ['code' => 'OTROS_EGRESOS', 'name' => 'OTROS EGRESOS', 'type' => 'EGRESO', 'status' => true,],
        ];
        /* |-------------------------------------------------------------------------- | CREAR CATEGORÍAS PARA CADA EMPRESA |-------------------------------------------------------------------------- */
        foreach ($companies as $company) {
            foreach ($categories as $category) {
                $exists = DB::table('cash_categories')->where('id_company', $company->id_company)->where('code', $category['code'])->exists();
                if ($exists) {
                    continue;
                }
                DB::table('cash_categories')->insert(['id_company' => $company->id_company, 'code' => $category['code'], 'name' => $category['name'], 'type' => $category['type'], 'status' => $category['status'], 'created_at' => now(), 'updated_at' => now(),]);
            }
        }
    }
}
