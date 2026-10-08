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
        //
         Schema::create('siat_configuraciones', function (Blueprint $table) {
            $table->id();

            // Empresa
            $table->string('id_company', 50);

            // Configuración general SIAT
            $table->unsignedTinyInteger('codigo_ambiente')->default(2);
            $table->unsignedTinyInteger('codigo_modalidad')->default(1);
            $table->string('codigo_sistema', 50);

            // Datos tributarios
            $table->string('nit');

            // Configuración por defecto de facturación
            $table->unsignedInteger('codigo_emision')->default(1);
            $table->unsignedInteger('tipo_factura_documento')->default(1);
            $table->unsignedInteger('codigo_documento_sector')->default(1);

            // Credenciales / integración
            $table->text('token_delegado')->nullable();

            // Estado
            $table->boolean('activo')->default(true);

            $table->timestamps();

            // FK empresa
            $table->foreign('id_company')
                ->references('id_company')
                ->on('companies')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // Una configuración SIAT por empresa
            $table->unique('id_company');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
        Schema::dropIfExists('siat_configuraciones');
    }
};
