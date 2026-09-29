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
        Schema::create('availability_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('schedule_id');
            $table->integer('day');
            $table->integer('startMinute');
            $table->integer('endMinute');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('availability_rules');
    }
};
