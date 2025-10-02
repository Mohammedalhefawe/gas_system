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
            $table->string('phone_number')->unique();
            $table->string('password');
            $table->boolean('is_verified')->default(false);
            $table->foreignId('role_id')->constrained('roles', 'role_id');
            $table->timestamps(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
