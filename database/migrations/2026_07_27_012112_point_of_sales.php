<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('point_of_sales', function (Blueprint $table) {

            // ID interno
            $table->id();

            // Empresa propietaria
            $table->string('id_company', 50);

            // Sucursal
            $table->foreignId('branch_id')
                ->constrained('branches')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // Código visible
            $table->string('codigo', 50);
            

            // Nombre del POS
            $table->string('name', 150);

            // Estado
            $table->boolean('status')->default(true);

            $table->timestamps();

         

            // FK empresa
            $table->foreign('id_company')
                ->references('id_company')
                ->on('companies')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_of_sales');
    }
};