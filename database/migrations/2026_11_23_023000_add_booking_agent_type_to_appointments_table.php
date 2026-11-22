<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('appointments')) {
            return;
        }

        if (! Schema::hasColumn('appointments', 'booking_agent_type')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->string('booking_agent_type', 32)
                    ->nullable()
                    ->after('booking_agent_id')
                    ->index();
            });

            DB::table('appointments')
                ->whereNotNull('booking_agent_id')
                ->whereNull('booking_agent_type')
                ->update(['booking_agent_type' => 'agent']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('appointments') && Schema::hasColumn('appointments', 'booking_agent_type')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->dropIndex(['booking_agent_type']);
                $table->dropColumn('booking_agent_type');
            });
        }
    }
};
