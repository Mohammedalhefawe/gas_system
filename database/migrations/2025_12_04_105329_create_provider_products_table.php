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
        Schema::create('provider_products', function (Blueprint $table) {
            $table->id('id');

            $table->unsignedBigInteger('provider_id');
            $table->unsignedBigInteger('product_id');

            $table->boolean('is_available')->default(true);

            $table->timestamps();

            $table->foreign('provider_id')
                ->references('provider_id')->on('providers')
                ->onDelete('cascade');

            $table->foreign('product_id')
                ->references('product_id')->on('products')
                ->onDelete('cascade');

            $table->unique(['provider_id', 'product_id']); // منع التكرار
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_products');
    }
};
