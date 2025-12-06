<?php
// database/migrations/2025_12_06_000001_add_provider_flow_to_orders_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'provider_id')) {
                $table->unsignedBigInteger('provider_id')->nullable()->after('driver_id');
                $table->foreign('provider_id')->references('provider_id')->on('providers')->onDelete('set null');
            }

            // تحديث عمود order_status ليدعم الحالات الجديدة
            DB::statement("ALTER TABLE orders MODIFY COLUMN order_status ENUM(
                'pending_provider',
                'pending_driver', 
                'accepted',
                'on_the_way_provider',
                'on_the_way_customer',
                'completed',
                'rejected',
                'cancelled'
            ) DEFAULT 'pending_provider'");
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['provider_id']);
            $table->dropColumn('provider_id');

            DB::statement("ALTER TABLE orders MODIFY COLUMN order_status ENUM(
                'pending', 'accepted', 'on_the_way', 'completed', 'cancelled', 'rejected'
            ) DEFAULT 'pending'");
        });
    }
};
