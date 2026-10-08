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
         Schema::create('companies', function (Blueprint $table) {
            $table->string('id_company')->primary();
        
            $table->string('business_name');
             $table->string('razon_social')->nullable();
            $table->string('tax_id',50)->unique();
            $table->string('website')->nullable();
            $table->string('logo')->nullable();
             $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }//
    
};
