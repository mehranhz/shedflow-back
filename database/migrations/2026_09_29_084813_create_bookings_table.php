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
        Schema::create('bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('parent')->references('id')->on('booking')->nullable();
            $table->foreignUuid('user_id');
            $table->foreignUuid('event_type_id');
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->integer('buffer_before_minutes')->nullable();
            $table->integer('buffer_after_minutes')->nullable();
            $table->string('timezone')->default('UTC')->nullable();
            $table->string('booking_status')->nullable();
            $table->string('booking_source')->nullable();
            $table->timestamp('expires_at');
            $table->json('meta_data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
