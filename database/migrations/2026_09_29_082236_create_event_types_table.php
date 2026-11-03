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
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('host_user_id')->constrained('users');
            $table->foreignUuid('schedule_id')->constrained();
            $table->string('slug');
            $table->string('title');
            $table->text('description')->default('');
            $table->unsignedInteger('duration_minutes');
            $table->string('location_type');
            $table->string('location_value')->nullable();
            $table->unsignedInteger('buffer_before_minutes')->default(0);
            $table->unsignedInteger('buffer_after_minutes')->default(0);
            $table->unsignedInteger('min_notice_minutes')->default(60);
            $table->unsignedInteger('max_days_ahead')->default(60);
            $table->unsignedInteger('slot_interval_minutes')->default(0);
            $table->unsignedInteger('daily_cap')->nullable();
            $table->boolean('requires_confirmation')->default(false);
            $table->unsignedInteger('cancellation_notice_hours')->default(24);
            $table->unsignedInteger('reschedule_notice_hours')->default(24);
            $table->json('questions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();

            $table->unique(['organization_id', 'slug']);
            $table->index(['organization_id', 'host_user_id']);
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
