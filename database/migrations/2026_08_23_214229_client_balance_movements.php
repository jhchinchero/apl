<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_balance_movements', function (Blueprint $table) {

            $table->id();

            $table->string('id_company', 50);

            $table->foreignId('client_id')
                ->constrained('clients');

            $table->foreignId('sale_id')
                ->nullable()
                ->constrained('sales')
                ->nullOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->enum('type', [
                'CREDITO',
                'USO_SALDO',
                'AJUSTE',
                'DEVOLUCION',
                'PAGO'
            ]);

            $table->decimal(
                'amount',
                12,
                2
            );

            $table->decimal(
                'balance_before',
                12,
                2
            );

            $table->decimal(
                'balance_after',
                12,
                2
            );

            $table->text('description')
                ->nullable();

            $table->dateTime('date');

            $table->timestamps();

            $table->foreign('id_company')
                ->references('id_company')
                ->on('companies')
                ->cascadeOnDelete();

            $table->index([
                'client_id',
                'date'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_balance_movements');
    }
};