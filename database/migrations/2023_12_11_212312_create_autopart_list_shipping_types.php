<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAutopartListShippingTypes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('autopart_list_shipping_type', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        $shippingType = [
            ['id' => 1, 'name' => 'Gratis'],
            ['id' => 2, 'name' => 'Cargo al cliente'],
            ['id' => 3, 'name' => 'Acordar con cliente'],
        ];

        DB::table('autopart_list_shipping_type')->insert($shippingType);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('autopart_list_shipping_type');
    }
}