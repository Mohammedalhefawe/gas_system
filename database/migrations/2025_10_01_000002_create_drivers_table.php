<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table) {
            $table->id('driver_id');
            $table->string('full_name');
            $table->string('phone_number')->unique();
            $table->string('password');
            $table->string('vehicle_type');
            $table->string('license_number')->unique();
            $table->boolean('is_available')->default(true);
            $table->string('current_location')->nullable();
            $table->decimal('rating', 3, 2)->default(0.0);
            $table->string('max_capacity')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
