<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateActorTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('actors', function (Blueprint $table) {
            $table->increments('id');
            $table->string('translated_name');
            $table->string('origin_name');
            $table->string('person_link');
            $table->string('poster_url');
            $table->date('date_of_birth');
            $table->string('place_of_birth');
            $table->text('additional_info')->nullable();
            $table->index('origin_name');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('actors');
    }
}
