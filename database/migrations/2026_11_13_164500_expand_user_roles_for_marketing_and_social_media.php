<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expand `users.role` from a rigid 5-value ENUM to VARCHAR(50) so first-class
     * roles (`marketing_specialist`, `social_media_editor`, etc.) store cleanly
     * across both MySQL and SQLite without CHECK / ENUM truncation failures.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 50)->default('user')->change();
        });
    }

    /**
     * Reverse the migrations safely without breaking rows using the new roles.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 50)->default('user')->change();
        });
    }
};
