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
        // ============================================================
        // FACTURA CABECERA
        // ============================================================

        Schema::create('factura_cabecera', function (Blueprint $table) {

            $table->id();

            // ================= ESTADO =================
            $table->integer('estado')->default(1);

            // ================= SIAT EMISOR =================
            $table->string('nit_emisor');
            $table->string('razon_social_emisor');
            $table->string('municipio');
            $table->string('telefono')->nullable();

            // ================= FACTURA =================
            $table->integer('numero_factura');

            $table->string('cuf')->nullable();
            $table->string('cufd');

            $table->string('codigo_sucursal');

            $table->string('direccion');

            $table->string('codigo_punto_venta');

            // ================= FECHA =================
            $table->dateTime('fecha_emision');

            // ================= CLIENTE =================
            $table->string('nombre_razon_social');

            $table->integer('codigo_tipo_documento_identidad');

            $table->string('numero_documento');

            $table->string('complemento')->nullable();

            $table->string('codigo_cliente');

            // ================= PAGO =================
            $table->integer('codigo_metodo_pago');

            $table->string('numero_tarjeta')->nullable();

            // ================= MONTO =================
            $table->decimal('monto_total', 10, 2);

            $table->decimal('monto_total_sujeto_iva', 10, 2);

            $table->integer('codigo_moneda');

            $table->decimal('tipo_cambio', 10, 2);

            $table->decimal('monto_total_moneda', 10, 2);

            $table->decimal('monto_gift_card', 10, 2)->nullable();

            $table->decimal('descuento_adicional', 10, 2)->nullable();

            // ================= SIAT CONTROL =================
            $table->integer('codigo_excepcion')->nullable();

            $table->string('cafc')->nullable();

            // ================= OTROS =================
            $table->string('leyenda');

            $table->string('usuario');
            $table->string('pdf_path')->nullable();

            $table->integer('codigo_documento_sector');

            // ========================================================
            // PUNTO DE VENTA
            // ========================================================

            $table->foreignId('punto_venta_id')
                ->constrained('point_of_sales')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
                $table->string('id_company');

$table->foreign('id_company')
    ->references('id_company')
    ->on('companies')
    ->cascadeOnUpdate()
    ->restrictOnDelete();

            $table->timestamps();

            // Índices útiles
            $table->index('cuf');
            $table->index('cufd');
            $table->index('numero_factura');
            $table->index('codigo_sucursal');
            $table->index('codigo_punto_venta');
            $table->index('fecha_emision');
        });


        // ============================================================
        // FACTURA DETALLE
        // ============================================================

        Schema::create('factura_detalle', function (Blueprint $table) {

            $table->id();

            // ================= FACTURA =================

            $table->foreignId('factura_id')
                ->constrained('factura_cabecera')
                ->cascadeOnDelete();

            // ================= PRODUCTO / SERVICIO =================

            $table->string('actividad_economica');

            // Código Producto/Servicio SIN
            $table->unsignedBigInteger('codigo_producto_sin');

            // Código interno de tu sistema
            $table->string('codigo_producto');

            $table->text('descripcion');

            $table->decimal('cantidad', 10, 2);

            $table->string('unidad_medida');

            $table->decimal('precio_unitario', 10, 2);

            $table->decimal('monto_descuento', 10, 2)->nullable();

            $table->decimal('sub_total', 10, 2);

            // ================= SERIAL / DISPOSITIVO =================

            $table->string('numero_serie')->nullable();

            $table->string('numero_imei')->nullable();

            $table->timestamps();

            // Índices
            //$table->index('codigo_producto_sin');
            //$table->index('codigo_producto');
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Primero detalle porque tiene FK hacia cabecera
        Schema::dropIfExists('factura_detalle');

        Schema::dropIfExists('factura_cabecera');
    }
};