<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_shift_logs', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'date']);
            $table->unique(['user_id', 'date', 'shift']);
        });
    }

    public function down(): void
    {
        Schema::table('agent_shift_logs', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'date', 'shift']);
            $table->unique(['user_id', 'date']);
        });
    }
};
