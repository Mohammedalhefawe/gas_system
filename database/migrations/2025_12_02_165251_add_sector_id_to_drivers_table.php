<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->unsignedBigInteger('sector_id')->nullable()->after('user_id');

            // Foreign key reference
            $table->foreign('sector_id')
                ->references('sector_id')   // Primary key in sectors table
                ->on('sectors')
                ->onDelete('set null');     // Or cascade, choose what you want
        });
    }

    public function down()
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropForeign(['sector_id']);
            $table->dropColumn('sector_id');
        });
    }
};
