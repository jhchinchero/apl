<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_expirations', function (Blueprint $table) {
            $table->id();

            // Producto
            $table->unsignedBigInteger('product_id');

            // Lote
            $table->string('lot', 100);

            // Fecha de vencimiento
            $table->date('expiration_date');

            $table->timestamps();

            // Relación con producto
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Evita repetir el mismo lote para el mismo producto
            $table->unique([
                'product_id',
                'lot'
            ]);
           
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(
            'product_expirations'
        );
    }
};