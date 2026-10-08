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
        Schema::create('branches', function (Blueprint $table) {

            // ID interno autoincremental
            $table->id();

            // Código visible
            $table->string('code_branch', 50);
             $table->integer('codigo');

            // Empresa
            $table->string('id_company', 50);

            // Estado
            $table->boolean('status')->default(true);

            // Información
            $table->string('branch_name', 100);

            $table->string('department', 100)->nullable();

            $table->string('municipality', 100)->nullable();

            $table->string('address')->nullable();

            $table->string('contact_name', 100)->nullable();

            $table->string('phone', 45)->nullable();

            $table->timestamps();

            // Empresa
            $table->foreign('id_company')
                ->references('id_company')
                ->on('companies')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Código único por empresa
            $table->unique([
                'id_company',
                'code_branch'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
        Schema::dropIfExists('branches');
    }
};
