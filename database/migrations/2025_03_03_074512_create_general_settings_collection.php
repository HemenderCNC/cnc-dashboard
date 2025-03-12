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
        Schema::create('general_settings', function (Blueprint $collection) {
            $collection->index('_id');
            $collection->string('site_title')->nullable();
            $collection->embedded('logo', function (Blueprint $sub) {
                $sub->string('standard')->nullable();
                $sub->string('small')->nullable();
            });
            $collection->embedded('leave_settings', function (Blueprint $sub) {
                $sub->integer('total_leaves_per_person')->default(20);
            });
            $collection->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('general_settings');
    }
};