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
            $table->string('autopart_number')->nullable()->after('name');
            $table->integer('position_id')->nullable()->unsigned()->after('category_id');
            $table->foreign('position_id')->references('id')->on('autopart_list_positions');
            $table->integer('side_id')->nullable()->unsigned()->after('position_id');
            $table->foreign('side_id')->references('id')->on('autopart_list_sides');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
