<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_stock', function (Blueprint $table) {

            // ID interno
            $table->id();

            // Empresa
            $table->string('id_company', 50);

            // Sucursal
            $table->foreignId('branch_id')
                ->constrained('branches')
                ->cascadeOnDelete();

            // Producto
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            // Stock actual
            $table->decimal(
                'current_stock',
                12,
                2
            )->default(0);

            // Estado
            $table->boolean('status')
                ->default(true);

            // Precio
            $table->decimal(
                'price',
                12,
                2
            )->default(0);

            // Ubicación
            $table->string(
                'location',
                100
            )->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | COMPANY FK
            |--------------------------------------------------------------------------
            */

            $table->foreign('id_company')
                ->references('id_company')
                ->on('companies')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | EVITAR DUPLICADO
            |--------------------------------------------------------------------------
            */

            $table->unique([
                'id_company',
                'branch_id',
                'product_id'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_stock');
    }
};