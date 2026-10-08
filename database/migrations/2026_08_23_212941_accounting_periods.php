<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_periods', function (Blueprint $table) {

            /*
            |--------------------------------------------------------------------------
            | ID
            |--------------------------------------------------------------------------
            */
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | EMPRESA
            |--------------------------------------------------------------------------
            */
            $table->string('id_company', 50);

            /*
            |--------------------------------------------------------------------------
            | SUCURSAL
            |--------------------------------------------------------------------------
            |
            | NULL = período general de toda la empresa.
            | Con valor = período específico de una sucursal.
            |
            */
            $table->unsignedBigInteger('branch_id')->nullable();

            /*
            |--------------------------------------------------------------------------
            | TIPO DE PERÍODO
            |--------------------------------------------------------------------------
            */
            $table->string('type', 20)->default('MONTHLY');

            /*
            |--------------------------------------------------------------------------
            | PERÍODO
            |--------------------------------------------------------------------------
            |
            | Ejemplo:
            | 2026-09
            |
            */
            $table->string('period', 7);

            /*
            |--------------------------------------------------------------------------
            | FECHAS
            |--------------------------------------------------------------------------
            */
            $table->date('start_date');
            $table->date('end_date');

            /*
            |--------------------------------------------------------------------------
            | ESTADO
            |--------------------------------------------------------------------------
            */
            $table->string('status', 20)->default('OPEN');

            /*
            |--------------------------------------------------------------------------
            | CIERRE
            |--------------------------------------------------------------------------
            */
            $table->timestamp('closed_at')->nullable();

            $table->unsignedBigInteger('closed_by')->nullable();

            /*
            |--------------------------------------------------------------------------
            | TIMESTAMPS
            |--------------------------------------------------------------------------
            */
            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | FOREIGN KEYS
            |--------------------------------------------------------------------------
            */

            // Empresa
            $table->foreign('id_company')
                ->references('id_company')
                ->on('companies')
                ->onDelete('cascade');

            // Sucursal
            $table->foreign('branch_id')
                ->references('id')
                ->on('branches')
                ->onDelete('cascade');

            // Usuario que cerró el período
            $table->foreign('closed_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | INDEX
            |--------------------------------------------------------------------------
            */
            $table->index([
                'id_company',
                'period',
                'status'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_periods');
    }
};