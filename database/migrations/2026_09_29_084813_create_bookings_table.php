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
            $table->string('uid')->unique();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('event_type_id')->constrained();
            $table->foreignUuid('host_user_id')->constrained('users');
            $table->foreignUuid('customer_id')->constrained();
            $table->timestamp('start_at');
            $table->timestamp('end_at');
            $table->unsignedInteger('buffer_before_minutes')->default(0);
            $table->unsignedInteger('buffer_after_minutes')->default(0);
            $table->string('timezone');
            $table->string('status');
            $table->string('source');
            $table->string('location_type');
            $table->string('location_value')->nullable();
            $table->json('answers')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->foreignUuid('rescheduled_from_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->timestamp('hold_expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'start_at']);
            $table->index(['host_user_id', 'start_at']);
            $table->index(['event_type_id', 'start_at']);
            $table->index('customer_id');
            $table->index(['status', 'hold_expires_at']);
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
