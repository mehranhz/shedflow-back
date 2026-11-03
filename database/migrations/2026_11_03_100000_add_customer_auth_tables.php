<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'email')) {
                $table->string('email')->nullable()->unique()->after('phone');
            }
            if (! Schema::hasColumn('users', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable()->after('phone_verified_at');
            }
        });

        if (! Schema::hasTable('customer_claim_tokens')) {
            Schema::create('customer_claim_tokens', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('email');
                $table->string('token_hash')->unique();
                $table->timestamp('expires_at');
                $table->timestamp('used_at')->nullable();
                $table->timestamps();

                $table->index('email');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_claim_tokens');

        Schema::table('users', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('users', 'email') ? 'email' : null,
                Schema::hasColumn('users', 'email_verified_at') ? 'email_verified_at' : null,
            ]));
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
