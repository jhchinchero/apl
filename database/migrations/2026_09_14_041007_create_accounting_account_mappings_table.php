<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_account_mappings', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | EMPRESA
            |--------------------------------------------------------------------------
            */
            $table->string('id_company', 50);

            /*
            |--------------------------------------------------------------------------
            | IDENTIFICADOR DEL MAPPING
            |--------------------------------------------------------------------------
            |
            | Ejemplos:
            |
            | VENTA
            | CUENTA_POR_COBRAR
            | COSTO_VENTA
            | INVENTARIO
            | DEVOLUCION_VENTA
            |
            */
            $table->string('key', 50);

            /*
            |--------------------------------------------------------------------------
            | NOMBRE
            |--------------------------------------------------------------------------
            */
            $table->string('name', 150);

            /*
            |--------------------------------------------------------------------------
            | CUENTA CONTABLE
            |--------------------------------------------------------------------------
            */
            $table->foreignId('accounting_account_id')
                ->constrained('accounting_accounts')
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | DESCRIPCIÓN
            |--------------------------------------------------------------------------
            */
            $table->text('description')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | ESTADO
            |--------------------------------------------------------------------------
            */
            $table->boolean('status')
                ->default(true);

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | EMPRESA
            |--------------------------------------------------------------------------
            */
            $table->foreign('id_company')
                ->references('id_company')
                ->on('companies')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | ÍNDICES
            |--------------------------------------------------------------------------
            */

            $table->unique(
                ['id_company', 'key'],
                'accounting_account_mappings_company_key_unique'
            );

            $table->index(
                ['id_company', 'status'],
                'accounting_account_mappings_company_status_index'
            );

            $table->index(
                ['id_company', 'accounting_account_id'],
                'accounting_account_mappings_company_account_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'accounting_account_mappings'
        );
    }
};