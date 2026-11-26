<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('legal_tasks') && !Schema::hasColumn('legal_tasks', 'completed_at')) {
            Schema::table('legal_tasks', function (Blueprint $table) {
                $table->timestamp('completed_at')->nullable()->after('status');
            });

            DB::table('legal_tasks')
                ->where('status', 'completed')
                ->whereNull('completed_at')
                ->update([
                    'completed_at' => DB::raw('COALESCE(updated_at, created_at)'),
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('legal_tasks') && Schema::hasColumn('legal_tasks', 'completed_at')) {
            Schema::table('legal_tasks', function (Blueprint $table) {
                $table->dropColumn('completed_at');
            });
        }
    }
};
