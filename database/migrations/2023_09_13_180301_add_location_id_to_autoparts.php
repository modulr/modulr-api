<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLocationIdToAutoparts extends Migration
{
    public function up()
    {
        Schema::table('autoparts', function (Blueprint $table) {
            $table->unsignedInteger('location_id')->nullable()->after('description');

            $table->foreign('location_id')->references('id')->on('autopart_list_locations');
        });
    }

    public function down()
    {
        Schema::table('autoparts', function (Blueprint $table) {
            $table->dropColumn('location_id');
        });
    }
}

