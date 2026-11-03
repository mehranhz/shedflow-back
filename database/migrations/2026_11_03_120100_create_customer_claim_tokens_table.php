<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_claim_tokens')) {
            return;
        }

        Schema::create('customer_claim_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email');
            $table->string('token_hash')->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_claim_tokens');
    }
};
