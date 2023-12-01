<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAutopartListBulbTechTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('autopart_list_bulb_tech', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        $bulbTechData = [
            ['id' => 1, 'name' => 'Halógeno'],
            ['id' => 2, 'name' => 'Xenón'],
            ['id' => 3, 'name' => 'LED'],
        ];

        DB::table('autopart_list_bulb_tech')->insert($bulbTechData);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('autopart_list_bulb_tech');
    }
}
