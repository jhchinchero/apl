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
          Schema::create('stock_movements', function (Blueprint $table) {

    $table->id();

    /*
    |--------------------------------------------------------------------------
    | EMPRESA
    |--------------------------------------------------------------------------
    */

    $table->string('id_company', 50);

    /*
    |--------------------------------------------------------------------------
    | SUCURSAL
    |--------------------------------------------------------------------------
    */

    $table->foreignId('branch_id')
        ->constrained('branches')
        ->restrictOnDelete();

    /*
    |--------------------------------------------------------------------------
    | PRODUCTO
    |--------------------------------------------------------------------------
    */

    $table->foreignId('product_id')
        ->constrained('products')
        ->restrictOnDelete();

    /*
    |--------------------------------------------------------------------------
    | USUARIO
    |--------------------------------------------------------------------------
    */

    $table->foreignId('user_id')
        ->constrained('users')
        ->restrictOnDelete();

    /*
    |--------------------------------------------------------------------------
    | TIPO
    |--------------------------------------------------------------------------
    */

    $table->enum('movement_type', [
        'INITIAL_STOCK',
        'ENTRY',//ENTRADA 
        'SALE',
        'RETURN',
        'ADJUSTMENT',//AJUSTE
        'TRANSFER_IN',
        'TRANSFER_OUT'
    ]);

    /*
    |--------------------------------------------------------------------------
    | CANTIDAD
    |--------------------------------------------------------------------------
    */

    $table->decimal(
        'quantity',
        12,
        2
    );

    /*
    |--------------------------------------------------------------------------
    | REFERENCIA
    |--------------------------------------------------------------------------
    |
    | Ejemplo:
    |
    | sale
    | purchase
    | transfer
    | adjustment
    |
    */

    $table->string(
        'reference_type',
        50
    )->nullable();


    /*
    |--------------------------------------------------------------------------
    | ID DEL DOCUMENTO
    |--------------------------------------------------------------------------
    |
    | Aquí guardarás el ID autoincremental.
    |
    | Ejemplo:
    |
    | sale_id = 15
    | transfer_id = 22
    |
    */

    $table->unsignedBigInteger(
        'reference_id'
    )->nullable();


    /*
    |--------------------------------------------------------------------------
    | NOTA
    |--------------------------------------------------------------------------
    */

    $table->text('note')
        ->nullable();


    $table->timestamps();


    /*
    |--------------------------------------------------------------------------
    | EMPRESA FK
    |--------------------------------------------------------------------------
    */

    $table->foreign('id_company')
        ->references('id_company')
        ->on('companies')
        ->cascadeOnUpdate()
        ->restrictOnDelete();


    /*
    |--------------------------------------------------------------------------
    | ÍNDICES
    |--------------------------------------------------------------------------
    */

    $table->index([
        'product_id',
        'branch_id'
    ]);

});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
        Schema::dropIfExists('stock_movements');
    }
};


/*
<?php

namespace App\Domains\Inventario\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Domains\Empresa\Models\Company;
use App\Domains\Empresa\Models\Branch;
use App\Models\User;

class StockMovement extends Model
{
    use HasFactory;

    protected $table = 'stock_movements';

    protected $fillable = [

        'id_company',

        'branch_id',

        'product_id',

        'user_id',

        'movement_type',

        'quantity',

        'reference_type',

        'reference_id',

        'note'

    ];


    protected $casts = [

        'quantity' => 'decimal:2'

    ];




    public function company()
    {
        return $this->belongsTo(
            Company::class,
            'id_company',
            'id_company'
        );
    }



    public function branch()
    {
        return $this->belongsTo(
            Branch::class,
            'branch_id',
            'id'
        );
    }



    public function product()
    {
        return $this->belongsTo(
            Product::class,
            'product_id',
            'id'
        );
    }




    public function user()
    {
        return $this->belongsTo(
            User::class,
            'user_id',
            'id'
        );
    }
}
*/