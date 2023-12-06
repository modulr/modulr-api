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
        Schema::table('autoparts_ml', function (Blueprint $table) {
            $table->unsignedBigInteger('condition_id')->nullable()->after('origin_id');
            $table->foreign('condition_id')->references('id')->on('autopart_list_conditions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('autoparts_ml', function (Blueprint $table) {
            $table->dropColumn('condition_id');
            $table->dropForeign(['user_id']);
        });
    }
};
