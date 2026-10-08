<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_branches', function (Blueprint $table) {

            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('branch_id')
                ->constrained('branches')
                ->cascadeOnDelete();

            $table->foreignId('point_of_sale_id')
                ->nullable()
                ->constrained('point_of_sales')
                ->nullOnDelete();

            $table->timestamps();

            // Un usuario no puede tener repetido
            // el mismo POS dentro de la misma sucursal.
           $table->unique([
    'user_id',
    'branch_id',
    'point_of_sale_id'
], 'user_branches_user_branch_pos_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_branches');
    }
};
