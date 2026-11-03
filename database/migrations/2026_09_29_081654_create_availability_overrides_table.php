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
            $table->foreignUuid('schedule_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->boolean('is_unavailable')->default(false);
            $table->unsignedSmallInteger('start_minute')->nullable();
            $table->unsignedSmallInteger('end_minute')->nullable();
            $table->timestamps();

            $table->unique(['schedule_id', 'date']);
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
