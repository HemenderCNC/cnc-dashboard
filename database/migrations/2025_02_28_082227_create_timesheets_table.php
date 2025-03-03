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
        Schema::create('timesheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects');
            $table->foreignId('task_id')->constrained();
            $table->date('date');
            $table->integer('hours')->unsigned();
            $table->integer('minutes')->unsigned();
            $table->text('work_description');
            // $table->foreignId('employee_id')->constrained('users')->onDelete('cascade'); 
            $table->foreignId('employee_id')->constrained('users'); 
            $table->text('updated_by');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('timesheets');
    }
};
