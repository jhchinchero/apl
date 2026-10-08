<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('income_expenses', function (Blueprint $table) {

            // =====================================================
            // ID
            // =====================================================

            $table->id();

            // =====================================================
            // EMPRESA
            // =====================================================

            $table->string('id_company', 50);

            // =====================================================
            // SUCURSAL
            // =====================================================

            $table->foreignId('branch_id')
                ->nullable()
                ->constrained('branches')
                ->nullOnDelete();

            // =====================================================
            // CAJA
            // =====================================================

            $table->foreignId('cash_register_id')
                ->nullable()
                ->constrained('cash_registers')
                ->nullOnDelete();

            // =====================================================
            // SESIÓN DE CAJA
            // =====================================================

            $table->foreignId('cash_register_session_id')
                ->nullable()
                ->constrained('cash_register_sessions')
                ->nullOnDelete();

            // =====================================================
            // USUARIO
            // =====================================================

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            // =====================================================
            // CATEGORÍA DE CAJA
            // =====================================================

            $table->foreignId('cash_category_id')
                ->nullable()
                ->constrained('cash_categories')
                ->nullOnDelete();

            // =====================================================
            // CUENTA CONTABLE
            // =====================================================
            //
            // Cuenta de contrapartida.
            //
            // INGRESO:
            //   CAJA              DEBE
            //   CUENTA INGRESO    HABER
            //
            // EGRESO:
            //   CUENTA GASTO      DEBE
            //   CAJA              HABER
            //

            $table->foreignId('accounting_account_id')
                ->constrained('accounting_accounts')
                ->restrictOnDelete();

            // =====================================================
            // PERÍODO CONTABLE
            // =====================================================

            $table->foreignId('accounting_period_id')
                ->constrained('accounting_periods')
                ->restrictOnDelete();

            // =====================================================
            // MOVIMIENTO DE CAJA
            // =====================================================

            $table->foreignId('cash_movement_id')
                ->nullable()
                ->constrained('cash_movements')
                ->nullOnDelete();

            // =====================================================
            // ASIENTO CONTABLE
            // =====================================================

            $table->foreignId('accounting_entry_id')
                ->nullable()
                ->constrained('accounting_entries')
                ->nullOnDelete();

            // =====================================================
            // CÓDIGO
            // =====================================================

            $table->string('code', 50);

            // =====================================================
            // FECHA
            // =====================================================

            $table->date('date');

            // =====================================================
            // TIPO
            // =====================================================

            $table->enum('type', [
                'INGRESO',
                'EGRESO'
            ]);

            // =====================================================
            // MONTO
            // =====================================================

            $table->decimal('amount', 14, 2);

            // =====================================================
            // DESCRIPCIÓN
            // =====================================================

            $table->text('description')->nullable();

            // =====================================================
            // ESTADO
            // =====================================================

            $table->enum('status', [
                'POSTED',
                'VOIDED'
            ])->default('POSTED');

            // =====================================================
            // TIMESTAMPS
            // =====================================================

            $table->timestamps();

            // =====================================================
            // FOREIGN KEY EMPRESA
            // =====================================================

            $table->foreign('id_company')
                ->references('id_company')
                ->on('companies')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // =====================================================
            // ÍNDICES
            // =====================================================

            $table->unique(
                ['id_company', 'code'],
                'income_expenses_company_code_unique'
            );

            $table->index(
                ['id_company', 'date'],
                'income_expenses_company_date_index'
            );

            $table->index(
                ['id_company', 'type', 'status'],
                'income_expenses_company_type_status_index'
            );

            $table->index(
                ['id_company', 'accounting_period_id'],
                'income_expenses_company_period_index'
            );

            $table->index(
                ['cash_register_session_id', 'type'],
                'income_expenses_session_type_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('income_expenses');
    }
};