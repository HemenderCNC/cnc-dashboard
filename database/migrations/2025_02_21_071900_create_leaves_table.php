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
        Schema::create('leaves', function (Blueprint $table) {
            $table->id();  // MongoDB does not use auto-increment IDs, but Laravel still adds this
            $table->foreignId('employee_id'); // Employee who requested leave
            $table->date('start_date');  // Start Date of leave
            $table->date('end_date');  // End Date of leave
            $table->integer('leave_duration'); // Total leave days
            $table->boolean('half_day')->default(false); // If leave is half-day
            $table->string('half_day_type')->nullable(); // first_half or second_half
            $table->text('reason');  // Reason for leave request
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->string('leave_type')->nullable(); // Assigned by HR
            $table->text('approve_comment')->nullable(); // HR's approval/rejection comment
            $table->string('approved_by')->nullable(); // HR or Manager who approved/rejected the leave
            $table->timestamps();  // created_at and updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leaves');
    }
};
