<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMlStatusToAutoparts extends Migration
{
    public function up()
    {
        Schema::table('autoparts', function (Blueprint $table) {
            $table->string('ml_status')->nullable()->after('ml_id');
        });
    }

    public function down()
    {
        Schema::table('autoparts', function (Blueprint $table) {
            $table->dropColumn('ml_status');
        });
    }
}
