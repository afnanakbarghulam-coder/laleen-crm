<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Safely upgrade any legacy utf8 / utf8mb3 (or non-utf8mb4) MySQL tables & columns
     * to utf8mb4 / utf8mb4_unicode_ci so 4-byte Unicode characters (fancy WhatsApp fonts
     * like "𝑨mna" and emojis like "🌸💅") never trigger MySQL Error 3988.
     *
     * Runs ONLY on MySQL / MariaDB (safely skipped on SQLite test runs).
     */
    public function up(): void
    {
        if (!in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $databaseName = (string) DB::getDatabaseName();
        if ($databaseName === '' || !preg_match('/^[A-Za-z0-9_\-]+$/', $databaseName)) {
            return;
        }

        // Best-effort database default charset/collation upgrade (ignore if DB user lacks schema-level ALTER).
        try {
            DB::statement("ALTER DATABASE `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (\Throwable $e) {
            // Continue with table/column-level conversion even if schema ALTER is restricted on Forge.
        }

        // 1. All base tables whose default TABLE_COLLATION is not utf8mb4_*
        $nonUtf8mb4Tables = DB::select(
            "SELECT TABLE_NAME
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ?
               AND TABLE_TYPE = 'BASE TABLE'
               AND TABLE_NAME != 'migrations'
               AND (TABLE_COLLATION IS NULL OR TABLE_COLLATION NOT LIKE 'utf8mb4_%')",
            [$databaseName]
        );

        // 2. All base tables containing ANY character/text column that is not utf8mb4
        //    OR core tables (appointments, customers, leads) whose text columns have mismatched collation
        $columnTables = DB::select(
            "SELECT DISTINCT c.TABLE_NAME
             FROM information_schema.COLUMNS c
             INNER JOIN information_schema.TABLES t
                ON t.TABLE_SCHEMA = c.TABLE_SCHEMA AND t.TABLE_NAME = c.TABLE_NAME
             WHERE c.TABLE_SCHEMA = ?
               AND t.TABLE_TYPE = 'BASE TABLE'
               AND c.TABLE_NAME != 'migrations'
               AND c.CHARACTER_SET_NAME IS NOT NULL
               AND (
                   c.CHARACTER_SET_NAME != 'utf8mb4'
                   OR c.COLLATION_NAME NOT LIKE 'utf8mb4_%'
                   OR (
                       c.TABLE_NAME IN ('appointments', 'customers', 'leads')
                       AND c.COLLATION_NAME != 'utf8mb4_unicode_ci'
                   )
               )",
            [$databaseName]
        );

        $tablesToConvert = [];
        foreach (array_merge($nonUtf8mb4Tables, $columnTables) as $row) {
            $name = (string) ($row->TABLE_NAME ?? '');
            if ($name !== '' && preg_match('/^[A-Za-z0-9_]+$/', $name)) {
                $tablesToConvert[$name] = true;
            }
        }

        if (empty($tablesToConvert)) {
            return;
        }

        // Prioritize core client/booking tables first
        $orderedTables = array_keys($tablesToConvert);
        usort($orderedTables, function (string $a, string $b): int {
            $priority = ['customers' => 0, 'appointments' => 1, 'leads' => 2];
            $pa = $priority[$a] ?? 100;
            $pb = $priority[$b] ?? 100;

            return $pa === $pb ? strcmp($a, $b) : ($pa <=> $pb);
        });

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($orderedTables as $tableName) {
                try {
                    DB::statement("ALTER TABLE `{$tableName}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                } catch (\Throwable $firstError) {
                    // Fallback for legacy COMPACT tables with 255-char indexed varchars: switch to DYNAMIC first
                    DB::statement("ALTER TABLE `{$tableName}` ROW_FORMAT=DYNAMIC, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                }
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive: reverting from utf8mb4 back to utf8mb3 would corrupt 4-byte Unicode data.
    }
};
