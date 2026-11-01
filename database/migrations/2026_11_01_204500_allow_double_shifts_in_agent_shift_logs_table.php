<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL requires an index starting with `user_id` to satisfy the
        // `user_id` foreign key constraint at all times — add the new
        // 3-column unique index FIRST before dropping the old 2-column one.
        Schema::table('agent_shift_logs', function (Blueprint $table) {
            $table->unique(['user_id', 'date', 'shift']);
        });

        Schema::table('agent_shift_logs', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::table('agent_shift_logs', function (Blueprint $table) {
            $table->unique(['user_id', 'date']);
        });

        Schema::table('agent_shift_logs', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'date', 'shift']);
        });
    }
};
