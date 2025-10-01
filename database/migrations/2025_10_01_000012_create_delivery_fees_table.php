<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_fees', function (Blueprint $table) {
            $table->id('fee_id');
            $table->decimal('fee', 10, 2); // قيمة الرسوم
            $table->date('date'); // تاريخ التفعيل
            $table->timestamps(); // created_at و updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_fees');
    }
};
