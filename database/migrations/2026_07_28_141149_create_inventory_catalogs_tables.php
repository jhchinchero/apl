<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Categories
        |--------------------------------------------------------------------------
        */
        Schema::create('categories', function (Blueprint $table) {

            /*
            |--------------------------------------------------------------------------
            | ID interno
            |--------------------------------------------------------------------------
            */

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Código visible
            |--------------------------------------------------------------------------
            | Ejemplo:
            | CAT-00001
            */

            $table->string('code_category', 50);

            /*
            |--------------------------------------------------------------------------
            | Empresa propietaria
            |--------------------------------------------------------------------------
            */

            $table->string('id_company');

            /*
            |--------------------------------------------------------------------------
            | Estado
            |--------------------------------------------------------------------------
            */

            $table->boolean('status')->default(true);

            /*
            |--------------------------------------------------------------------------
            | Nombre
            |--------------------------------------------------------------------------
            */

            $table->string('category_name', 100);

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Relación con companies
            |--------------------------------------------------------------------------
            */

            $table->foreign('id_company')
                ->references('id_company')
                ->on('companies')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Código único por empresa
            |--------------------------------------------------------------------------
            |
            | Empresa A:
            | CAT-00001
            | CAT-00002
            |
            | Empresa B:
            | CAT-00001
            | CAT-00002
            |
            */

            $table->unique([
                'id_company',
                'code_category'
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Brands
        |--------------------------------------------------------------------------
        */

       Schema::create('brands', function (Blueprint $table) {

    /*
    |--------------------------------------------------------------------------
    | ID interno
    |--------------------------------------------------------------------------
    */

    $table->id();


    /*
    |--------------------------------------------------------------------------
    | Código visible
    |--------------------------------------------------------------------------
    |
    | Ejemplo:
    |
    | BRA-00001
    | BRA-00002
    |
    | Correlativo por empresa.
    |
    */

    $table->string('code_brand', 50);


    /*
    |--------------------------------------------------------------------------
    | Empresa
    |--------------------------------------------------------------------------
    */

    $table->string('id_company', 50);


    /*
    |--------------------------------------------------------------------------
    | Estado
    |--------------------------------------------------------------------------
    */

    $table->boolean('status')
        ->default(true);


    /*
    |--------------------------------------------------------------------------
    | Nombre
    |--------------------------------------------------------------------------
    */

    $table->string(
        'brand_name',
        100
    );


    /*
    |--------------------------------------------------------------------------
    | Timestamps
    |--------------------------------------------------------------------------
    */

    $table->timestamps();


    /*
    |--------------------------------------------------------------------------
    | Relación con empresa
    |--------------------------------------------------------------------------
    */

    $table->foreign('id_company')
        ->references('id_company')
        ->on('companies')
        ->cascadeOnUpdate()
        ->cascadeOnDelete();


    /*
    |--------------------------------------------------------------------------
    | Código único por empresa
    |--------------------------------------------------------------------------
    */

    $table->unique([
        'id_company',
        'code_brand'
    ]);
});

        /*
        |--------------------------------------------------------------------------
        | Product Models
        |--------------------------------------------------------------------------
        */


        /*
        |--------------------------------------------------------------------------
        | Suppliers
        |--------------------------------------------------------------------------
        */
Schema::create('suppliers', function (Blueprint $table) {

    /*
    |--------------------------------------------------------------------------
    | ID interno
    |--------------------------------------------------------------------------
    */

    $table->id();


    /*
    |--------------------------------------------------------------------------
    | Código visible
    |--------------------------------------------------------------------------
    |
    | Ejemplo:
    |
    | SUP-00001
    | SUP-00002
    |
    */

    $table->string('code_supplier', 50);


    /*
    |--------------------------------------------------------------------------
    | Empresa
    |--------------------------------------------------------------------------
    */

    $table->string('id_company', 50);


    /*
    |--------------------------------------------------------------------------
    | Estado
    |--------------------------------------------------------------------------
    */

    $table->boolean('status')
        ->default(true);


    /*
    |--------------------------------------------------------------------------
    | Información
    |--------------------------------------------------------------------------
    */

    $table->string(
        'name',
        100
    );

    $table->string(
        'email'
    )->nullable();

    $table->string(
        'phone',
        50
    )->nullable();

    $table->string(
        'address'
    )->nullable();

    $table->string(
        'reference'
    )->nullable();


    /*
    |--------------------------------------------------------------------------
    | Timestamps
    |--------------------------------------------------------------------------
    */

    $table->timestamps();


    /*
    |--------------------------------------------------------------------------
    | Relación con empresa
    |--------------------------------------------------------------------------
    */

    $table->foreign('id_company')
        ->references('id_company')
        ->on('companies')
        ->cascadeOnUpdate()
        ->cascadeOnDelete();


    /*
    |--------------------------------------------------------------------------
    | Código único por empresa
    |--------------------------------------------------------------------------
    */

    $table->unique([
        'id_company',
        'code_supplier'
    ]);
});

        Schema::create('type_data', function (Blueprint $table) {

            // PK interna de Laravel
            $table->id();

            // Código visible del tipo de dato
            // Ejemplo: TD-00001
            $table->string('id_type_data', 50);

            // Empresa propietaria
            $table->string('id_company');

            // Estado
            $table->boolean('status')->default(true);

            // Nombre del tipo de dato
            $table->string('type_name', 100);

            // Código adicional
            $table->string('code', 100)->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Relación con companies
            |--------------------------------------------------------------------------
            */

            $table->foreign('id_company')
                ->references('id_company')
                ->on('companies')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Código único por empresa
            |--------------------------------------------------------------------------
            |
            | Una empresa no puede tener dos TD-00001,
            | pero diferentes empresas sí pueden tener TD-00001.
            |
            */

            $table->unique([
                'id_company',
                'id_type_data'
            ]);
        });

        Schema::create('units', function (Blueprint $table) {
            // PK interna
            $table->id();
            // Código de la unidad
            // Ejemplo: UNI-00001
            $table->string('code_unit', 50)->unique();
            // Nombre de la unidad
            // Ejemplo: Kilogramo
            $table->string('unit_name', 100);
            // Símbolo
            // Ejemplo: kg
            $table->string('symbol', 20)->nullable();
            // Descripción
            $table->text('description')->nullable();
            // Estado
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');

        Schema::dropIfExists('brands');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('type_data');
        Schema::dropIfExists('units');
    }
};
