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
            $table->foreignId('user_id')->constrained('users', 'user_id');
            $table->string('device_token');
            $table->string('device_type');
            $table->string('app_version')->nullable();
            $table->timestamp('last_active')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};
