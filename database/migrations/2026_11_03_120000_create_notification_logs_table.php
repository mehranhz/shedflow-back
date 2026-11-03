<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel');
            $table->string('template');
            $table->string('to_address');
            $table->string('provider_id')->nullable();
            $table->string('status');
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['booking_id', 'template']);
            $table->index(['organization_id', 'created_at']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement(
                'CREATE UNIQUE INDEX notification_logs_booking_template_channel_uidx '
                .'ON notification_logs (booking_id, template, channel) '
                .'WHERE booking_id IS NOT NULL'
            );
        } else {
            Schema::table('notification_logs', function (Blueprint $table) {
                $table->unique(['booking_id', 'template', 'channel'], 'notification_logs_booking_template_channel_uidx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS notification_logs_booking_template_channel_uidx');
        }

        Schema::dropIfExists('notification_logs');
    }
};
