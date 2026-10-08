<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_payment_mappings', function (Blueprint $table) {

            $table->id();

            $table->string('id_company', 50);

            $table->string('payment_method', 30);

            $table->foreignId('accounting_account_id')
                ->constrained('accounting_accounts')
                ->restrictOnDelete();

            $table->boolean('status')
                ->default(true);

            $table->timestamps();

            $table->foreign('id_company')
                ->references('id_company')
                ->on('companies')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->unique(
                ['id_company', 'payment_method'],
                'accounting_payment_mapping_unique'
            );

            $table->index(
                ['id_company', 'status'],
                'accounting_payment_mapping_company_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'accounting_payment_mappings'
        );
    }
};