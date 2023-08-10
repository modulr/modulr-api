<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveColumnsFromAutopartImagesTable extends Migration
{
    public function up()
    {
        Schema::table('autopart_images', function (Blueprint $table) {
            $table->dropColumn(['created_by', 'updated_by', 'deleted_by', 'deleted_at']);
        });
    }

    public function down()
    {
        Schema::table('autopart_images', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
    }
}

