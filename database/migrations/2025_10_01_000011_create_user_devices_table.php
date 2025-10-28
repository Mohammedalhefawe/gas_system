<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_devices', function (Blueprint $table) {
            $table->id('device_id');
            $table->unsignedBigInteger('user_id');
            $table->string('device_token');
            $table->string('device_type'); // android or ios
            $table->string('app_version')->nullable();
            $table->timestamp('last_active')->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};
