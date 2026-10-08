<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | TABLA SALES
        |--------------------------------------------------------------------------
        */

        Schema::create('sales', function (Blueprint $table) {

            // ID interno autoincremental
            $table->id();

            // Código visible de la venta
            // Ejemplo: VEN-00001
            $table->string('code_sale', 50);

            // Empresa
            $table->string('id_company', 50);

            // Número de comprobante/factura
            $table->string('receipt_number', 100)->nullable();

            // Fecha de la venta
            $table->dateTime('date');

            // Descuento total de la venta
            $table->decimal('discount', 12, 2)->default(0);

            // Total final
            $table->decimal('total', 12, 2)->default(0);

            // URL o ruta del PDF
            $table->string('pdf_url')->nullable();

            /*
            |--------------------------------------------------------------------------
            | USUARIO
            |--------------------------------------------------------------------------
            */

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | CLIENTE
            |--------------------------------------------------------------------------
            */

            $table->foreignId('client_id')
                ->constrained('clients')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | SUCURSAL
            |--------------------------------------------------------------------------
            */

            // $table->string('id_branch', 50);

            // Sucursal
            $table->foreignId('branch_id')
                ->constrained('branches')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | RELACIÓN CON EMPRESA
            |--------------------------------------------------------------------------
            */

            $table->foreign('id_company')
                ->references('id_company')
                ->on('companies')
                ->cascadeOnDelete();
            $table->unique(
                ['id_company', 'code_sale'],
                'sales_company_code_sale_unique'
            );
            $table->timestamps();
        });


        /*
        |--------------------------------------------------------------------------
        | TABLA SALE_DETAILS
        |--------------------------------------------------------------------------
        */

        Schema::create('sale_details', function (Blueprint $table) {

            // ID interno autoincremental
            $table->id();

            // Código visible del detalle
            // Ejemplo: VD-00001
            $table->string('code_sale_detail', 50);

            /*
            |--------------------------------------------------------------------------
            | PRODUCTO
            |--------------------------------------------------------------------------
            */
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | VENTA
            |--------------------------------------------------------------------------
            */
            $table->foreignId('sale_id')
                ->constrained('sales')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | DATOS DEL PRODUCTO AL MOMENTO DE LA VENTA
            |--------------------------------------------------------------------------
            */

            // Cantidad vendida
            $table->decimal('quantity', 12, 2);

            // Precio unitario
            $table->decimal('unit_price', 12, 2);
            // Subtotal antes de descuento/impuestos
            $table->decimal('subtotal', 12, 2);
            // Descuento
            $table->decimal('discount', 12, 2)->default(0);
            // IVA
            $table->decimal('iva', 12, 2)->default(0);
            // Total de esta línea
            $table->decimal('total', 12, 2);
            /*
            |--------------------------------------------------------------------------
            | DEVOLUCIONES
            |--------------------------------------------------------------------------
            */
            $table->boolean('returned')->default(false);
            $table->decimal('returned_quantity', 12, 2)
                ->default(0);

            $table->timestamps();
        });
    }


    /**
     * Revertir migraciones.
     */
    public function down(): void
    {
        // Primero la tabla hija
        Schema::dropIfExists('sale_details');

        // Después la tabla padre
        Schema::dropIfExists('sales');
    }
};
