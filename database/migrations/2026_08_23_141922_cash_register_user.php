<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_register_user', function (Blueprint $table) {

            // =====================================================
            // ID
            // =====================================================
            $table->id();

            // =====================================================
            // EMPRESA
            // =====================================================
            $table->string('id_company', 50);

            // =====================================================
            // CAJA
            // =====================================================
            $table->foreignId('cash_register_id')
                ->constrained('cash_registers')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // =====================================================
            // USUARIO
            // =====================================================
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // =====================================================
            // ESTADO DE LA ASIGNACIÓN
            // =====================================================
            $table->boolean('status')->default(true);

            $table->timestamps();

            // =====================================================
            // FK EMPRESA
            // =====================================================
            $table->foreign('id_company')
                ->references('id_company')
                ->on('companies')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // =====================================================
            // EVITAR DUPLICAR USUARIO EN LA MISMA CAJA
            // =====================================================
            $table->unique(
                ['cash_register_id', 'user_id'],
                'cash_register_user_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_register_user');
    }
};