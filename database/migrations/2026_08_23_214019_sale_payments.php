<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_payments', function (Blueprint $table) {

            // =====================================================
            // ID
            // =====================================================

            $table->id();


            // =====================================================
            // EMPRESA
            // =====================================================

            $table->string('id_company', 50);


            // =====================================================
            // VENTA
            // =====================================================

            $table->foreignId('sale_id')
                ->constrained('sales')
                ->cascadeOnDelete();


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
    ->nullable()                           // ← AGREGADO
    ->constrained('cash_register_sessions')
    ->cascadeOnUpdate()
    ->nullOnDelete();     


            // =====================================================
            // MÉTODO DE PAGO
            // =====================================================

            $table->enum('payment_method', [
                'CASH',
                'CARD',
                'TRANSFER',
                'QR',
                'CHECK',
                'BALANCE',
                'OTHER'
            ]);


            // =====================================================
            // MONTO
            // =====================================================

            $table->decimal('amount', 12, 2);


            // =====================================================
            // REFERENCIA
            // =====================================================

            $table->string('reference')
                ->nullable();


            // =====================================================
            // OBSERVACIÓN
            // =====================================================

            $table->text('observation')
                ->nullable();


            // =====================================================
            // FECHAS
            // =====================================================

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
            // ÍNDICE PRINCIPAL
            // =====================================================

            $table->index(
                ['id_company', 'sale_id'],
                'sale_payments_company_sale_index'
            );


            // =====================================================
            // ÍNDICE POR SESIÓN
            // =====================================================

            $table->index(
                ['cash_register_session_id', 'payment_method'],
                'sale_payments_session_method_index'
            );


            // =====================================================
            // ÍNDICE POR CAJA
            // =====================================================

            $table->index(
                ['cash_register_id', 'payment_method'],
                'sale_payments_register_method_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_payments');
    }
};