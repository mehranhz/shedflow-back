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
        Schema::create('availability_overrides', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('schedule_id');
            $table->timestamp('date')->nullable();
            $table->boolean('is_unavailable')->default(false);
            $table->integer('day');
            $table->integer('startMinute');
            $table->integer('endMinute');
            $table->text('notes')->nullable();
            $table->string('public_label')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('availability_overrides');
    }
};
