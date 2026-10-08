<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_returns', function (Blueprint $table) {

            // =====================================================
            // ID
            // =====================================================

            $table->id();

            // =====================================================
            // CÓDIGO DEVOLUCIÓN
            // =====================================================

            $table->string('code_return', 50);

            // =====================================================
            // EMPRESA
            // =====================================================

            $table->string('id_company', 50);

            // =====================================================
            // VENTA ORIGINAL
            // =====================================================

            $table->foreignId('sale_id')
                ->constrained('sales')
                ->restrictOnDelete();

            // =====================================================
            // CLIENTE
            // =====================================================

            $table->foreignId('client_id')
                ->constrained('clients')
                ->restrictOnDelete();

            // =====================================================
            // SUCURSAL
            // =====================================================

            $table->foreignId('branch_id')
                ->constrained('branches')
                ->restrictOnDelete();

            // =====================================================
            // USUARIO QUE REALIZA DEVOLUCIÓN
            // =====================================================

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

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
                ->restrictOnDelete();

            // =====================================================
            // TIPO DE DEVOLUCIÓN
            // =====================================================

            $table->enum('refund_type', [
                'MONEY',
                'BALANCE'
            ]);

            // =====================================================
            // MÉTODO DE DEVOLUCIÓN
            // =====================================================

            $table->enum('refund_method', [
                'CASH',
                'CARD',
                'TRANSFER',
                'QR',
                'OTHER'
            ])->nullable();

            // =====================================================
            // MOTIVO
            // =====================================================

            $table->text('reason')->nullable();

            // =====================================================
            // TOTAL DEVUELTO
            // =====================================================

            $table->decimal('total_amount', 12, 2)
                ->default(0);

            // =====================================================
            // ESTADO
            // =====================================================

            $table->enum('status', [
                'COMPLETED',
                'CANCELLED'
            ])->default('COMPLETED');

            // =====================================================
            // FECHA
            // =====================================================

            $table->dateTime('date');

            // =====================================================
            // EMPRESA FK
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
                ['id_company', 'code_return'],
                'sale_returns_company_code_unique'
            );

            $table->index(
                ['id_company', 'sale_id'],
                'sale_returns_company_sale_index'
            );

            $table->index(
                ['id_company', 'client_id'],
                'sale_returns_company_client_index'
            );

            $table->index(
                ['cash_register_session_id'],
                'sale_returns_session_index'
            );

            $table->timestamps();
        });
         Schema::create('sale_return_items', function (Blueprint $table) {

            // =====================================================
            // ID
            // =====================================================

            $table->id();

            // =====================================================
            // DEVOLUCIÓN
            // =====================================================

            $table->foreignId('sale_return_id')
                ->constrained('sale_returns')
                ->cascadeOnDelete();

            // =====================================================
            // DETALLE ORIGINAL DE VENTA
            // =====================================================

            $table->foreignId('sale_detail_id')
                ->constrained('sale_details')
                ->restrictOnDelete();

            // =====================================================
            // PRODUCTO
            // =====================================================

            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();

            // =====================================================
            // CANTIDAD DEVUELTA
            // =====================================================

            $table->decimal('quantity', 12, 2);

            // =====================================================
            // PRECIO ORIGINAL
            // =====================================================

            $table->decimal('unit_price', 12, 2);

            // =====================================================
            // SUBTOTAL
            // =====================================================

            $table->decimal('subtotal', 12, 2);

            // =====================================================
            // DESCUENTO
            // =====================================================

            $table->decimal('discount', 12, 2)
                ->default(0);

            // =====================================================
            // IVA
            // =====================================================

            $table->decimal('iva', 12, 2)
                ->default(0);

            // =====================================================
            // TOTAL
            // =====================================================

            $table->decimal('total', 12, 2);

            // =====================================================
            // MOTIVO DEL ITEM
            // =====================================================

            $table->text('reason')->nullable();

            $table->timestamps();

            // =====================================================
            // ÍNDICES
            // =====================================================

            $table->index(
                ['sale_detail_id'],
                'sale_return_items_detail_index'
            );

            $table->index(
                ['product_id'],
                'sale_return_items_product_index'
            );
        });
    

    
    }

    public function down(): void
    {
         Schema::dropIfExists('sale_return_items');
        Schema::dropIfExists('sale_returns');
       
    }
};