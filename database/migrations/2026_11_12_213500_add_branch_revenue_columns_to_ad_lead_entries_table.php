<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ad_lead_entries')) {
            return;
        }

        Schema::table('ad_lead_entries', function (Blueprint $table) {
            if (!Schema::hasColumn('ad_lead_entries', 'old_airport_amount')) {
                $table->decimal('old_airport_amount', 10, 2)->nullable()->after('ticket_amount');
            }
            if (!Schema::hasColumn('ad_lead_entries', 'wakrah_amount')) {
                $table->decimal('wakrah_amount', 10, 2)->nullable()->after('old_airport_amount');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('ad_lead_entries')) {
            return;
        }

        Schema::table('ad_lead_entries', function (Blueprint $table) {
            $dropCols = [];
            if (Schema::hasColumn('ad_lead_entries', 'old_airport_amount')) {
                $dropCols[] = 'old_airport_amount';
            }
            if (Schema::hasColumn('ad_lead_entries', 'wakrah_amount')) {
                $dropCols[] = 'wakrah_amount';
            }
            if (!empty($dropCols)) {
                $table->dropColumn($dropCols);
            }
        });
    }
};
