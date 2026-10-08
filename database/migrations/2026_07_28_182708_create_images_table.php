<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('images', function (Blueprint $table) {

            // ID autoincremental
            $table->id();

        

            // Producto relacionado
            $table->unsignedBigInteger('product_id');

            // Ruta de la imagen
            $table->string('image_path', 500);

            $table->timestamps();

            // Relación con products
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('images');
    }
};


//registro de imagen
/*
$file = $request->file('image');

$extension = $file->getClientOriginalExtension();

$number = ProductImage::where(
    'product_id',
    $product->id
)->count() + 1;

$fileName =
    $product->id_company
    . '-'
    . $product->id_product
    . '-'
    . $number
    . '.'
    . $extension;

$folder = 'products/' . $product->id_company;

$path = $file->storeAs(
    $folder,
    $fileName,
    'public'
);

$image = ProductImage::create([
    'product_id' => $product->id,
    'image_path' => $path,
]);
*/