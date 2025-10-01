<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
  Schema::create('users', function (Blueprint $table) {
    $table->id('user_id'); 
    $table->string('full_name');
    $table->string('phone_number')->unique();
    $table->string('email')->unique();
    $table->string('password');
    $table->boolean('is_verified')->default(false);
    $table->boolean('is_admin')->default(false);
    $table->timestamps();
});
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
