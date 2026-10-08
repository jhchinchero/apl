<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_entry_details', function (Blueprint $table) {

            $table->id();

            $table->foreignId('accounting_entry_id')
                ->constrained('accounting_entries')
                ->cascadeOnDelete();

            $table->foreignId('accounting_account_id')
                ->constrained('accounting_accounts');

            $table->string(
                'description',
                255
            )->nullable();

            $table->decimal(
                'debit',
                14,
                2
            )->default(0);

            $table->decimal(
                'credit',
                14,
                2
            )->default(0);

            $table->timestamps();

            $table->index(
                'accounting_account_id'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_entry_details');
    }
};