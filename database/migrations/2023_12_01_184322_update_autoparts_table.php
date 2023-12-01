<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateAutopartsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('autoparts', function (Blueprint $table) {
            // Agregar columna bulb_pos_id
            $table->unsignedBigInteger('bulb_pos_id')->nullable();
            $table->foreign('bulb_pos_id')->references('id')->on('autopart_list_bulb_pos');

            // Agregar columna bulb_tech_id
            $table->unsignedBigInteger('bulb_tech_id')->nullable();
            $table->foreign('bulb_tech_id')->references('id')->on('autopart_list_bulb_tech');

            // Agregar columna includes_mirror
            $table->boolean('includes_mirror')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('autoparts', function (Blueprint $table) {
            // Revertir los cambios en caso de rollback
            $table->dropForeign(['bulb_pos_id']);
            $table->dropColumn('bulb_pos_id');

            $table->dropForeign(['bulb_tech_id']);
            $table->dropColumn('bulb_tech_id');

            $table->dropColumn('includes_mirror');
        });
    }
}
