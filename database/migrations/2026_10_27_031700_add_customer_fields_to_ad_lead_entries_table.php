<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_lead_entries', function (Blueprint $table) {
            if (!Schema::hasColumn('ad_lead_entries', 'customer_id')) {
                $table->foreignId('customer_id')->nullable()->after('date')->constrained('customers')->nullOnDelete();
            }
            if (!Schema::hasColumn('ad_lead_entries', 'customer_name')) {
                $table->string('customer_name')->nullable()->after('customer_id');
            }
        });

        // Backfill existing ad_lead_entries with matching customer records by phone
        if (Schema::hasTable('customers')) {
            $entries = DB::table('ad_lead_entries')->whereNull('customer_name')->get(['id', 'phone']);
            foreach ($entries as $entry) {
                $customer = DB::table('customers')->where('phone', $entry->phone)->first(['id', 'name']);
                if ($customer) {
                    DB::table('ad_lead_entries')
                        ->where('id', $entry->id)
                        ->update([
                            'customer_id' => $customer->id,
                            'customer_name' => $customer->name,
                        ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('ad_lead_entries', function (Blueprint $table) {
            if (Schema::hasColumn('ad_lead_entries', 'customer_id')) {
                $table->dropConstrainedForeignId('customer_id');
            }
            if (Schema::hasColumn('ad_lead_entries', 'customer_name')) {
                $table->dropColumn('customer_name');
            }
        });
    }
};
