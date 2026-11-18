<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['monthly_ads', 'ecommerce_monthly_ads'] as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            // Convert rigid MySQL enum on monthly_ads.phase to flexible string if running on MySQL
            if ($tableName === 'monthly_ads' && DB::getDriverName() === 'mysql' && Schema::hasColumn($tableName, 'phase')) {
                DB::statement("ALTER TABLE `{$tableName}` MODIFY COLUMN `phase` VARCHAR(50) NOT NULL DEFAULT 'Planning'");
            }

            if (!Schema::hasColumn($tableName, 'creatives')) {
                $hasPhase = Schema::hasColumn($tableName, 'phase');
                Schema::table($tableName, function (Blueprint $table) use ($hasPhase) {
                    $col = $table->json('creatives')->nullable();
                    if ($hasPhase) {
                        $col->after('phase');
                    }
                });
            }

            // Consolidate legacy Production rows into the combined Planning & Production stage ('Planning')
            if (Schema::hasColumn($tableName, 'phase')) {
                DB::table($tableName)
                    ->whereIn(DB::raw('LOWER(phase)'), ['production', 'planning_production', 'planning & production'])
                    ->update(['phase' => 'Planning']);
            }

            // Non-destructive backfill of existing 1..3 creative columns into JSON `creatives`
            if (Schema::hasColumn($tableName, 'creatives') && Schema::hasColumn($tableName, 'creative_1_name')) {
                DB::table($tableName)
                    ->whereNull('creatives')
                    ->orderBy('id')
                    ->chunkById(200, function ($rows) use ($tableName) {
                        foreach ($rows as $row) {
                            $pairs = [
                                ['name' => $row->creative_1_name ?? null, 'url' => $row->creative_asset_link ?? null],
                                ['name' => $row->creative_2_name ?? null, 'url' => $row->creative_asset_link_2 ?? null],
                                ['name' => $row->creative_3_name ?? null, 'url' => $row->creative_asset_link_3 ?? null],
                            ];

                            $items = [];
                            $idx = 1;
                            foreach ($pairs as $p) {
                                $name = trim((string) ($p['name'] ?? ''));
                                $url = trim((string) ($p['url'] ?? ''));
                                if ($name !== '' || $url !== '') {
                                    $items[] = [
                                        'name'          => $name !== '' ? $name : "Creative {$idx}",
                                        'creative_type' => 'reel',
                                        'media_type'    => 'video',
                                        'size'          => '9:16',
                                        'reference_url' => filter_var($url, FILTER_VALIDATE_URL) ? $url : null,
                                    ];
                                    $idx++;
                                }
                            }

                            if (!empty($items)) {
                                DB::table($tableName)
                                    ->where('id', $row->id)
                                    ->update(['creatives' => json_encode($items)]);
                            }
                        }
                    });
            }
        }
    }

    public function down(): void
    {
        foreach (['monthly_ads', 'ecommerce_monthly_ads'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'creatives')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('creatives');
                });
            }
        }
    }
};
