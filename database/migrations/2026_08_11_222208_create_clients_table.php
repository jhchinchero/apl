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
        Schema::create('clients', function (Blueprint $table) { 
            // Internal ID 
            $table->id(); // Visible client code // Example: CLI-00001 
            $table->string('code_client', 50); 
            // Company / Tenant 
            $table->string('id_company', 50);
            $table->boolean('status')->default(true); 
            $table->string('name'); 
            $table->string('ciNit'); 
            $table->string('email')->nullable(); 
            $table->string('phone')->nullable(); 
            $table->string('address')->nullable(); 
            $table->string('person_reference')->nullable(); 
            $table->decimal('balance', 10, 2)->default(0); 
            $table->softDeletes(); 
            $table->timestamps(); 
            // Client code unique per company 
            $table->unique([ 'id_company', 'code_client' ]); 
            // CI/NIT unique per company 
            $table->unique([ 'id_company', 'ciNit' ]); 
            // Relation with existing companies table 
            $table->foreign('id_company') ->references('id_company') ->on('companies') ->onDelete('cascade'); }); 
        }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
