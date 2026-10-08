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

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->boolean('status')->default(1);
            $table->string('name');
            $table->string('email');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('phone')->nullable(); // <--- agregado
            $table->string('address')->nullable(); // <--- agregado
            $table->string('reference_person')->nullable(); // <--- agregado
            $table->foreignId('role_id')->constrained('roles'); // <--- relación

            // Relación con companies
            // Empresa
            $table->string('id_company');
            $table->foreign('id_company')
                ->references('id_company')
                ->on('companies')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->unique([
                'id_company',
                'email'
            ]);
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('accesses', function (Blueprint $table) {
            $table->id();
            // Empresa
            $table->string('id_company');

            //$table->foreignId('role_id')->constrained()->onDelete('cascade');
            $table->foreignId('module_id')->constrained()->onDelete('cascade');
            $table->boolean('can_view')->default(false);
            $table->boolean('can_create')->default(false);
            $table->boolean('can_edit')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->unsignedBigInteger('user_id');

            // Relación con companies
            $table->foreign('id_company')
                ->references('id_company')
                ->on('companies')
                ->onDelete('cascade');
            //$table->unique(['role_id', 'module_id']);//Así evitas registros duplicados y updateOrCreate funciona correctamente.

            $table->timestamps();
           
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Schema::dropIfExists('cash_register_sessions');
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('accesses');
    }
};
