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
       Schema::create('cash_categories', function (Blueprint $table) {

            $table->id();

            $table->string('id_company', 50);

            $table->string('code', 50);
            $table->string('name', 150);

            $table->enum('type', [
                'INGRESO',
                'EGRESO'
            ]);

            $table->foreignId('accounting_account_id')
                ->nullable()
                ->constrained('accounting_accounts')
                ->nullOnDelete();

            $table->boolean('status')
                ->default(true);

            $table->timestamps();

            $table->foreign('id_company')
                ->references('id_company')
                ->on('companies')
                ->cascadeOnDelete();

            $table->unique([
                'id_company',
                'code'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
        Schema::dropIfExists('cash_categories');
    }
};
