<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
         Schema::create('cash_movements', function (Blueprint $table) {

            $table->id();

            $table->string('id_company', 50);

            $table->foreignId('branch_id')
                ->constrained('branches');

            $table->foreignId('cash_register_id')
                ->constrained('cash_registers');

            $table->foreignId('cash_register_session_id')
                ->constrained('cash_register_sessions');

            /*
            | Se llena cuando la sesión es cerrada
            */
            $table->foreignId('cash_closure_id')
                ->nullable()
                ->constrained('cash_closures')
                ->nullOnDelete();

            $table->foreignId('user_id')
                ->constrained('users');

            $table->foreignId('cash_category_id')
                ->nullable()
                ->constrained('cash_categories')
                ->nullOnDelete();

            $table->enum('type', [
                'INGRESO',
                'EGRESO'
            ]);

            $table->decimal(
                'amount',
                12,
                2
            );

            /*
            | Referencias opcionales
            */

            $table->foreignId('sale_id')
                ->nullable()
                ->constrained('sales')
                ->nullOnDelete();

            $table->foreignId('sale_return_id')
                ->nullable()
                ->constrained('sale_returns')
                ->nullOnDelete();

            $table->foreignId('sale_payment_id')
                ->nullable()
                ->constrained('sale_payments')
                ->nullOnDelete();

            $table->text('description')
                ->nullable();

            $table->timestamps();

            $table->foreign('id_company')
                ->references('id_company')
                ->on('companies')
                ->cascadeOnDelete();

           $table->index(
    ['id_company', 'cash_register_id', 'cash_register_session_id'],
    'idx_cash_movements_session'
);

$table->index(
    ['type', 'created_at'],
    'idx_cash_movements_type_date'
);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
    }
};

/*
$table->enum('type', [
                'OPENING',
                'SALE',
                'REFUND',
                'INCOME',
                'WITHDRAWAL',
                'EXPENSE',
                'ADJUSTMENT',
                'CLOSING',
            ]);
*/