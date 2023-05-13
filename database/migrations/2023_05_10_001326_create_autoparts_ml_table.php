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
        Schema::create('autoparts_ml', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->float('sale_price')->nullable();

            $table->integer('make_id')->nullable()->unsigned();
            $table->foreign('make_id')->references('id')->on('autopart_list_makes');
            $table->integer('model_id')->nullable()->unsigned();
            $table->foreign('model_id')->references('id')->on('autopart_list_models');

            $table->integer('origin_id')->nullable()->unsigned();
            $table->foreign('origin_id')->references('id')->on('autopart_list_origins');

            $table->integer('status_id')->nullable()->unsigned();
            $table->foreign('status_id')->references('id')->on('autopart_list_status');

            $table->json('images')->nullable();
            $table->json('years')->nullable();
            $table->json('years_ids')->nullable();

            $table->string('ml_id')->unique();
            $table->integer('store_ml_id')->nullable()->unsigned();
            $table->foreign('store_ml_id')->references('id')->on('stores_ml');

            $table->boolean('import')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     */
    public function down(): void
    {
        Schema::dropIfExists('autoparts_ml');
    }
};
