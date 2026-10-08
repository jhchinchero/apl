<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_closures', function (Blueprint $table) {

            $table->id();

            $table->string('id_company', 50);

            $table->foreignId('branch_id')
                ->constrained('branches');

            $table->foreignId('cash_register_id')
                ->constrained('cash_registers');

            $table->foreignId('cash_register_session_id')
                ->unique()
                ->constrained('cash_register_sessions');

            $table->foreignId('user_id')
                ->constrained('users');

            $table->foreignId('closed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->dateTime('date');

            $table->decimal(
                'opening_amount',
                12,
                2
            );

            $table->decimal(
                'total_income',
                12,
                2
            )->default(0);

            $table->decimal(
                'total_expense',
                12,
                2
            )->default(0);

            $table->decimal(
                'expected_amount',
                12,
                2
            );

            $table->decimal(
                'counted_amount',
                12,
                2
            );

            $table->decimal(
                'difference',
                12,
                2
            );

            $table->enum('status', [
                'CLOSED',
                'REOPENED'
            ])->default('CLOSED');

            $table->timestamp('opening_at')
                ->nullable();

            $table->timestamp('closed_at')
                ->nullable();

            $table->text('note')
                ->nullable();

            $table->timestamps();

            $table->foreign('id_company')
                ->references('id_company')
                ->on('companies')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_closures');
    }
};