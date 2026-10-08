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
         Schema::create('quotations', function (Blueprint $table) {

            // ID interno autoincremental
            $table->id();

            // Código visible de la cotización
            // Ejemplo: COT-000001
            $table->string('code_quotation', 50);

            // Empresa / Tenant
            $table->string('id_company', 50);

            // Fecha de la cotización
            $table->date('date');

            // Cliente opcional
            $table->unsignedBigInteger('client_id')->nullable();

            // Usuario que registró la cotización
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            // Total calculado por backend
            $table->decimal('total', 12, 2)->default(0);

            // Nombre del PDF generado
            $table->string('pdf_name')->nullable();

            // Datos del cliente guardados como referencia
            // aunque posteriormente cambie sus datos
            $table->string('client_name')->nullable();
            $table->string('client_nit')->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | COMPANY
            |--------------------------------------------------------------------------
            */

            $table->foreign('id_company')
                ->references('id_company')
                ->on('companies')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | CLIENT
            |--------------------------------------------------------------------------
            */

            $table->foreign('client_id')
                ->references('id')
                ->on('clients')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Código único por empresa
            |--------------------------------------------------------------------------
            */

            $table->unique([
                'id_company',
                'code_quotation'
            ]);
        });


        /*
        |--------------------------------------------------------------------------
        | DETALLES DE COTIZACIÓN
        |--------------------------------------------------------------------------
        */

        Schema::create('quotation_details', function (Blueprint $table) {

            // ID interno autoincremental
            $table->id();

            // Cotización
            $table->unsignedBigInteger('quotation_id');

            // Producto
            $table->unsignedBigInteger('product_id');

            // Cantidad
            $table->decimal('quantity', 12, 2);

            // Precio unitario
            $table->decimal('price', 12, 2);

            // Subtotal
            $table->decimal('subtotal', 12, 2);

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | QUOTATION
            |--------------------------------------------------------------------------
            */

            $table->foreign('quotation_id')
                ->references('id')
                ->on('quotations')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | PRODUCT
            |--------------------------------------------------------------------------
            */

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
         Schema::dropIfExists('quotation_details');
        Schema::dropIfExists('quotations');
    }
};
