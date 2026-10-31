<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migrate existing auto-closed rebooked leads to the explicit 'booked' status.
     */
    public function up(): void
    {
        if (!Schema::hasTable('leads')) {
            return;
        }

        DB::table('leads')
            ->where('customer_remarks', 'like', '%[Auto-closed: Rebooked%')
            ->update(['needful_done' => 'booked']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('leads')) {
            return;
        }

        DB::table('leads')
            ->where('needful_done', 'booked')
            ->update(['needful_done' => 'yes']);
    }
};
