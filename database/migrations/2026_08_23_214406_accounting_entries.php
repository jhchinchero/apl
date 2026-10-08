<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
         Schema::create('accounting_entries', function (Blueprint $table) {

            $table->id();

            $table->string('id_company', 50);

            $table->foreignId('branch_id')
                ->nullable()
                ->constrained('branches')
                ->nullOnDelete();

            $table->foreignId('accounting_period_id')
                ->constrained('accounting_periods');

            $table->string(
                'code_entry',
                50
            );

            $table->dateTime('date');

            $table->string(
                'type',
                50
            );

            $table->text('description')
                ->nullable();

            /*
            | Polimórfico:
            | Sale
            | CashMovement
            | SaleReturn
            | etc.
            */

            $table->string(
                'reference_type',
                100
            )->nullable();

            $table->unsignedBigInteger(
                'reference_id'
            )->nullable();

            $table->foreignId('user_id')
                ->constrained('users');

            $table->enum('status', [
                'DRAFT',
                'POSTED',
                'VOIDED'
            ])->default('POSTED');

            $table->timestamps();

            $table->foreign('id_company')
                ->references('id_company')
                ->on('companies')
                ->cascadeOnDelete();

            $table->unique([
                'id_company',
                'code_entry'
            ]);

            $table->index([
                'reference_type',
                'reference_id'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_entries');
    }
};