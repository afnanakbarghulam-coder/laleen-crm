<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Convert the `customers` table to utf8mb4_unicode_ci on MySQL / MariaDB so 4-byte
     * UTF-8 emojis in client names and legacy notes never trigger MySQL error 3988 / 1366.
     *
     * Index key-length safety (InnoDB 3,072-byte limit on MySQL 8.4):
     * - phone VARCHAR(255) unique => 1,020 bytes
     * - name VARCHAR(255) index => 1,020 bytes
     * - branch VARCHAR(60) index => 240 bytes
     * - status VARCHAR(32) index => 128 bytes
     * - import_source VARCHAR(64) index => 256 bytes
     */
    public function up(): void
    {
        if (!in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        if (!Schema::hasTable('customers')) {
            return;
        }

        DB::statement('ALTER TABLE `customers` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    public function down(): void
    {
        // Keep utf8mb4_unicode_ci on rollback to avoid truncating/failing on existing 4-byte UTF-8 data.
    }
};
