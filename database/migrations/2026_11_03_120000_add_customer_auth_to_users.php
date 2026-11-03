<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'email')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->unique()->after('phone');
            $table->timestamp('email_verified_at')->nullable()->after('phone_verified_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'email')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['email', 'email_verified_at']);
        });
    }
};
