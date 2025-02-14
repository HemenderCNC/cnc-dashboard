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
        Schema::create('project', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Original project name
            $table->string('priority'); // Priority 
            $table->string('client_name'); // Client name
            $table->timestamp('start_date')->nullable(); // Project start date
            $table->timestamp('end_date')->nullable(); // Project end date
            $table->json('select_employees'); // Store employees as JSON (array)
            $table->json('select_task_type'); // Store task types as JSON (array)
            $table->string('hours_type'); // Hours type
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project');
    }
};
