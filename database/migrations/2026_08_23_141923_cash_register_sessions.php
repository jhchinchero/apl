<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_register_sessions', function (Blueprint $table) {

            // =====================================================
            // ID INTERNO
            // =====================================================
            $table->id();

            // =====================================================
            // EMPRESA
            // =====================================================
            $table->string('id_company', 50);

              // Sucursal
            $table->foreignId('branch_id')
                ->constrained('branches')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // =====================================================
            // CAJA
            // =====================================================
            $table->foreignId('cash_register_id')
                ->constrained('cash_registers')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // =====================================================
            // USUARIO QUE ABRE LA CAJA
            // =====================================================
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // =====================================================
            // MONTOS
            // =====================================================

            // Dinero con el que inicia la caja
            $table->decimal('opening_amount', 12, 2)
                ->default(0);

            // Dinero que el sistema calcula que debería existir
            $table->decimal('expected_amount', 12, 2)
                ->default(0);

            // Dinero contado físicamente al cerrar
            $table->decimal('closing_amount', 12, 2)
                ->nullable();

            // Diferencia entre esperado y contado
            $table->decimal('difference', 12, 2)
                ->nullable();

            // =====================================================
            // FECHAS
            // =====================================================

            $table->timestamp('opened_at');

            $table->timestamp('closed_at')
                ->nullable();

            // =====================================================
            // ESTADO
            // =====================================================

            $table->enum('status', [
                'OPEN',
                'CLOSED'
            ])->default('OPEN');

            // =====================================================
            // OBSERVACIONES
            // =====================================================

            $table->text('opening_note')
                ->nullable();

            $table->text('closing_note')
                ->nullable();

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
            // ÍNDICES
            // =====================================================

            $table->index(
                ['id_company', 'cash_register_id'],
                'cash_sessions_company_cash_register_index'
            );

            $table->index(
                ['id_company', 'user_id'],
                'cash_sessions_company_user_index'
            );

            $table->index(
                ['cash_register_id', 'status'],
                'cash_sessions_cash_register_status_index'
            );

            $table->index(
                ['user_id', 'status'],
                'cash_sessions_user_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_register_sessions');
    }
};