<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {

            $table->string('codigo_siat', 50)
                ->nullable()
                ->after('product_code');

            $table->string('actividad_codigo', 50)
                ->nullable()
                ->after('codigo_siat');

        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {

            $table->dropColumn([
                'codigo_siat',
                'actividad_codigo',
            ]);

        });
    }
};