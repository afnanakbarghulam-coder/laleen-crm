<?php

use Database\Seeders\CrmPresentationSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('crm_presentations')) {
            return;
        }

        $playbook = CrmPresentationSeeder::giveawayPlaybookAttributes();

        DB::table('crm_presentations')
            ->where('slug', CrmPresentationSeeder::GIVEAWAY_SLUG)
            ->update([
                'title' => $playbook['title'],
                'slides' => json_encode($playbook['slides'], JSON_UNESCAPED_UNICODE),
                'summary' => $playbook['summary'],
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Non-destructive layout upgrade
    }
};
