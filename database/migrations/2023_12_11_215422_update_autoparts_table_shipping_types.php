<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateAutopartsTableShippingTypes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('autoparts', function (Blueprint $table) {
            $table->unsignedBigInteger('shipping_type_id')->nullable()->after('condition_id');
            $table->foreign('shipping_type_id')->references('id')->on('autopart_list_shipping_type');
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
            $table->dropForeign(['shipping_type_id']);
            $table->dropColumn('shipping_type_id');
        });
    }
}
