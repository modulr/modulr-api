<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAutopartListBulbPosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('autopart_list_bulb_pos', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        $bulbPosData = [
            ['id' => 1, 'name' => 'Inferior derecha'],
            ['id' => 2, 'name' => 'Inferior izquierda'],
            ['id' => 3, 'name' => 'Central inferior'],
            ['id' => 4, 'name' => 'Central superior'],
        ];

        DB::table('autopart_list_bulb_pos')->insert($bulbPosData);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('autopart_list_bulb_pos');
    }
}
