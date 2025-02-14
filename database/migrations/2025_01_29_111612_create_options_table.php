<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOptionsTable extends Migration
{
    public function up()
    {
        Schema::create('options', function (Blueprint $table) {
            $table->id();
            $table->string('category'); // e.g., 'qualification_levels', 'departments'
            $table->string('value');    // e.g., 'High School', 'IT Department'
            $table->string('group')->nullable(); // e.g., 'Development', 'Designations'
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('options');
    }
}
