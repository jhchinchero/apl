<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
          Schema::create('accounting_account_types', function (Blueprint $table) {

            $table->id();

            // Código del tipo contable
            // Ejemplo: ACT, PAS, PAT, ING, COS, GAS
            $table->string('code', 20)->unique();

            // Nombre
            // Ejemplo: ACTIVO, PASIVO, INGRESO
            $table->string('name', 100);

            // Naturaleza contable
            // DEUDORA / ACREEDORA
            $table->enum('nature', [
                'DEUDORA',
                'ACREEDORA',
            ]);

            // Estado
            // true  = activo
            // false = inactivo
            $table->boolean('status')->default(true);

            // Descripción opcional
            $table->string('description', 500)->nullable();

            $table->timestamps();

            // Índices
            $table->index('status');
            $table->index('nature');
        });
        Schema::create('accounting_accounts', function (Blueprint $table) {

            $table->id();

            $table->string('id_company', 50);

            $table->string('code', 30);
            $table->string('name', 150);
            $table->text('description')->nullable();

            $table->enum('type', [
                'ACTIVO',
                'PASIVO',
                'PATRIMONIO',
                'INGRESO',
                'GASTO',
                'COSTO'
            ]);

            $table->unsignedTinyInteger('level')
                ->default(1);

            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('accounting_accounts')
                ->nullOnDelete();

            $table->boolean('allows_entries')
                ->default(true);

            $table->boolean('status')
                ->default(true);

            $table->timestamps();
             $table->foreignId('account_type_id')
                ->constrained('accounting_account_types')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('id_company')
                ->references('id_company')
                ->on('companies')
                ->cascadeOnDelete();

            $table->unique([
                'id_company',
                'code'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_account_types');
        Schema::dropIfExists('accounting_accounts');
    }
};