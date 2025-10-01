<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
   Schema::create('orders', function (Blueprint $table) {
    $table->id('order_id');

    // أنشئ الأعمدة أولًا
    $table->unsignedBigInteger('user_id');
    $table->unsignedBigInteger('driver_id')->nullable();
    $table->unsignedBigInteger('address_id')->nullable();

    // الأعمدة الأخرى
    $table->decimal('total_amount', 10, 2);
    $table->decimal('delivery_fee', 10, 2)->default(0);
    $table->string('order_status');
    $table->text('delivery_address')->nullable();
    $table->timestamp('order_date')->useCurrent();
    $table->timestamp('delivery_date')->nullable();
    $table->time('delivery_time')->nullable();
    $table->string('payment_method');
    $table->string('payment_status');
    $table->text('special_instructions')->nullable();
    $table->integer('rating')->nullable();
    $table->text('review')->nullable();

    // بعدين اعمل الـ foreign keys
    $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
    $table->foreign('driver_id')->references('driver_id')->on('drivers')->onDelete('set null');
    $table->foreign('address_id')->references('address_id')->on('user_addresses')->onDelete('set null');
});


    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
