<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAutopartListLocationsTable extends Migration
{
    public function up()
    {
        Schema::create('autopart_list_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('stock');
            $table->unsignedInteger('store_id');
            $table->foreign('store_id')->references('id')->on('stores');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('autopart_list_locations');
    }
}

