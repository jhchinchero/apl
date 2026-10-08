<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        

        
        $this->call([
         RoleModuleUserAccess::class,
           BranchSeeder::class,
          CategorySeeder::class,
          BrandSeeder::class,
          SupplierSeeder::class,
          UnitOfMeasureSeeder::class,
         TypeDataSeeder::class,
        //CashCategorySeeder::class,
         //AccountingAccountSeeder::class,//SEEDER CREADO PORLA VISTA
         ProductSeeder::class,
       //ProductImageSeeder::class,

       
      



        // 1. Tipos contables
            //AccountingAccountTypeSeeder::class,

            // 2. Plan de cuentas
           

            // 3. Mapeos de operaciones
           // AccountingAccountMappingSeeder::class,

            // 4. Mapeos de métodos de pago
            //AccountingPaymentMappingSeeder::class,
        ]);
    }
}
