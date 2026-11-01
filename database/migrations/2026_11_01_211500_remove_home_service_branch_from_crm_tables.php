<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'appointments',
        'sales',
        'expenses',
        'ad_lead_entries',
        'staff_complaints',
        'kpi_staff_evaluations',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'branch')) {
                DB::table($table)
                    ->where('branch', 'home_service')
                    ->update(['branch' => 'old_airport']);
            }
        }

        if (DB::getDriverName() === 'mysql') {
            if (Schema::hasTable('appointments') && Schema::hasColumn('appointments', 'branch')) {
                DB::statement("ALTER TABLE `appointments` MODIFY COLUMN `branch` ENUM('old_airport', 'wakrah') NOT NULL DEFAULT 'old_airport'");
            }
            if (Schema::hasTable('sales') && Schema::hasColumn('sales', 'branch')) {
                DB::statement("ALTER TABLE `sales` MODIFY COLUMN `branch` ENUM('old_airport', 'wakrah') NULL");
            }
            if (Schema::hasTable('expenses') && Schema::hasColumn('expenses', 'branch')) {
                DB::statement("ALTER TABLE `expenses` MODIFY COLUMN `branch` ENUM('old_airport', 'wakrah') NULL");
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            if (Schema::hasTable('appointments') && Schema::hasColumn('appointments', 'branch')) {
                DB::statement("ALTER TABLE `appointments` MODIFY COLUMN `branch` ENUM('old_airport', 'wakrah', 'home_service') NOT NULL DEFAULT 'old_airport'");
            }
            if (Schema::hasTable('sales') && Schema::hasColumn('sales', 'branch')) {
                DB::statement("ALTER TABLE `sales` MODIFY COLUMN `branch` ENUM('old_airport', 'wakrah', 'home_service') NULL");
            }
            if (Schema::hasTable('expenses') && Schema::hasColumn('expenses', 'branch')) {
                DB::statement("ALTER TABLE `expenses` MODIFY COLUMN `branch` ENUM('old_airport', 'wakrah', 'home_service') NULL");
            }
        }
    }
};
