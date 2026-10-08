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
         Schema::create('cuis', function (Blueprint $table) {

            $table->id();

            // Código CUIS entregado por SIAT
            $table->string('codigo', 100);

            // Fecha de solicitud
            $table->dateTime('fecha_solicitada');

            // Fecha de vigencia
            $table->dateTime('fecha_vigencia');

            // Estado del CUIS
            $table->boolean('vigente')->default(true);

            // Punto de venta
            $table->foreignId('punto_venta_id')
                ->constrained('point_of_sales')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->timestamps();

            /*
$table->index([ 'punto_venta_id', 'vigente' ]);
            */
           
        });
         Schema::create('cufd', function (Blueprint $table) {

            $table->id();

            // Código CUFD
            $table->string('codigo', 255);

            // Código de control
            $table->string('codigo_control', 255);

            // Inicio de vigencia
            $table->dateTime('fecha_inicio');

            // Fin de vigencia
            $table->dateTime('fecha_vigencia');

            // Estado
            $table->boolean('vigente')->default(true);

            // Punto de venta
            $table->foreignId('punto_venta_id')
                ->constrained('point_of_sales')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
         Schema::dropIfExists('cufd');
          Schema::dropIfExists('cuis');
    }
};
