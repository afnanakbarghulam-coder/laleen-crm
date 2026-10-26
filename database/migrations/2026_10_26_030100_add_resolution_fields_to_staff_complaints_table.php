<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_complaints', function (Blueprint $table) {
            if (!Schema::hasColumn('staff_complaints', 'what_happened')) {
                $table->text('what_happened')->nullable()->after('description');
            }
            if (!Schema::hasColumn('staff_complaints', 'corrective_action')) {
                $table->text('corrective_action')->nullable()->after('what_happened');
            }
            if (!Schema::hasColumn('staff_complaints', 'is_staff_mistake')) {
                $table->boolean('is_staff_mistake')->nullable()->after('corrective_action');
            }
        });

        // Backfill existing complaints' what_happened from description
        DB::table('staff_complaints')
            ->whereNull('what_happened')
            ->update(['what_happened' => DB::raw('description')]);
    }

    public function down(): void
    {
        Schema::table('staff_complaints', function (Blueprint $table) {
            $drop = [];
            foreach (['what_happened', 'corrective_action', 'is_staff_mistake'] as $col) {
                if (Schema::hasColumn('staff_complaints', $col)) {
                    $drop[] = $col;
                }
            }
            if (!empty($drop)) {
                $table->dropColumn($drop);
            }
        });
    }
};
