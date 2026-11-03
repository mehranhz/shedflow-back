<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('phone');
            $table->string('role');
            $table->string('token_hash')->unique();
            $table->foreignUuid('invited_by_id')->constrained('users');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['organization_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
