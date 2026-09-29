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
        Schema::create('event_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug');
            $table->foreignUuid('creator_id')->references('id')->on('users');
            $table->foreignUuid('organizer_id')->references('id')->on('organizer');
            $table->foreignUuid('schedule_id')->references('id')->on('schedule');
            $table->foreignUuid('organization_id')->references('id')->on('organization');
            $table->text('notes')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false);
            $table->boolean('is_active')->default(false);
            $table->boolean('is_default')->default(false);
            $table->integer('duration_minutes')->nullable();
            $table->integer('buffer_before_minutes')->nullable();
            $table->integer('buffer_after_minutes')->nullable();
            $table->integer('min_notice_minutes')->nullable();
            $table->integer('max_days_ahead')->nullable();
            $table->integer('slot_interval_minutes')->nullable();
            $table->integer('daily_capacity')->nullable();
            $table->integer('weekly_capacity')->nullable();
            $table->integer('monthly_capacity')->nullable();
            $table->integer('yearly_capacity')->nullable();
            $table->boolean('require_confirmation')->nullable();
            $table->boolean('creator_can_cancel')->nullable();
            $table->boolean('organizer_can_cancel')->nullable();
            $table->boolean('hosts_can_cancel')->nullable();
            $table->boolean('guest_can_cancel')->nullable();
            $table->integer('min_minutes_before_cancel')->nullable();
            $table->integer('max_minutes_before_cancel')->nullable();
            $table->integer('cancellation_notice_minutes')->nullable();
            $table->integer('reschedule_notice_minutes')->nullable();
            $table->string('location_type')->nullable();
            $table->string('location')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_types');
    }
};
