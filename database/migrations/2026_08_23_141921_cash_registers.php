<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_registers', function (Blueprint $table) {

            $table->id();

            // Empresa
            $table->string('id_company', 50);

            // Sucursal
            $table->foreignId('branch_id')
                ->constrained('branches')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // Punto de venta
            $table->foreignId('point_of_sale_id')
                ->constrained('point_of_sales')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
                // Cuenta contable de la caja
$table->foreignId('accounting_account_id')
    ->nullable()
    ->constrained('accounting_accounts')
    ->nullOnDelete();

            // Código de caja
            $table->string('code', 50);

            // Nombre
            $table->string('name', 150);

            // Descripción
            $table->text('description')->nullable();

            // Estado
            $table->boolean('status')->default(true);

            $table->timestamps();

            // FK empresa
            $table->foreign('id_company')
                ->references('id_company')
                ->on('companies')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // Código único por empresa
            $table->unique(
                ['id_company', 'code'],
                'cash_registers_company_code_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_registers');
    }
};
