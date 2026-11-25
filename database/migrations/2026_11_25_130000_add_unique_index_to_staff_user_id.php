<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('staff')) {
            if (!Schema::hasColumn('staff', 'user_id')) {
                Schema::table('staff', function (Blueprint $table) {
                    $table->foreignId('user_id')->nullable()->after('commission_rate')->constrained('users')->nullOnDelete();
                });
            }

            // Deduplicate any accidental duplicate non-null user_id before adding unique index (keeping lowest staff id)
            $duplicates = DB::table('staff')
                ->select('user_id')
                ->whereNotNull('user_id')
                ->groupBy('user_id')
                ->havingRaw('COUNT(*) > 1')
                ->pluck('user_id');

            foreach ($duplicates as $dupUserId) {
                $keepId = DB::table('staff')->where('user_id', $dupUserId)->orderBy('id')->value('id');
                DB::table('staff')->where('user_id', $dupUserId)->where('id', '!=', $keepId)->update(['user_id' => null]);
            }

            $indexes = collect(Schema::getIndexes('staff'))->pluck('name')->all();
            if (!in_array('staff_user_id_unique', $indexes, true)) {
                Schema::table('staff', function (Blueprint $table) {
                    $table->unique('user_id', 'staff_user_id_unique');
                });
            }
        }

        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'is_active')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->after('role');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('staff')) {
            $indexes = collect(Schema::getIndexes('staff'))->pluck('name')->all();
            if (in_array('staff_user_id_unique', $indexes, true)) {
                Schema::table('staff', function (Blueprint $table) {
                    $table->dropUnique('staff_user_id_unique');
                });
            }
        }
    }
};
