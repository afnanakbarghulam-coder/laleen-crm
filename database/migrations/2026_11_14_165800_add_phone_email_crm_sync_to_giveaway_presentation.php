<?php

use Database\Seeders\CrmPresentationSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('crm_presentations')) {
            return;
        }

        (new CrmPresentationSeeder())->run();
    }

    public function down(): void
    {
        // Non-destructive refresh adding Contact Number + Email -> Auto-CRM sync slides
    }
};
