<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('siat_certificados', function (Blueprint $table) {
            $table->id();

            // Empresa propietaria del certificado
            $table->string('id_company', 50);

            // Archivos del certificado
            $table->string('certificado_path', 500);
            $table->string('llave_privada_path', 500)->nullable();  // ✅ nullable

            // Contraseña de la llave/certificado
            $table->text('password')->nullable();

            // Información del certificado
            $table->string('nombre', 150)->nullable();
            $table->string('serial', 255)->nullable();
            $table->dateTime('fecha_inicio')->nullable();
            $table->dateTime('fecha_vencimiento')->nullable();

            // Estado
            $table->boolean('activo')->default(true);

            // Auditoría
            $table->timestamps();

            $table->foreign('id_company')
                ->references('id_company')
                ->on('companies')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->index(['id_company', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siat_certificados');
    }
};