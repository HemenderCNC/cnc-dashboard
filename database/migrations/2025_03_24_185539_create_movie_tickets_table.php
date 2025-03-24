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
        Schema::create('movie_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('image'); // Path to uploaded image
            $table->date('date'); // Movie date
            $table->decimal('amount', 10, 2); // Ticket price
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movie_tickets');
    }
};
