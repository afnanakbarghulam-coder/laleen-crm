<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_complaints', function (Blueprint $table) {
            if (!Schema::hasColumn('staff_complaints', 'manager_feedback')) {
                $table->text('manager_feedback')->nullable()->after('corrective_action');
            }
        });
    }

    public function down(): void
    {
        Schema::table('staff_complaints', function (Blueprint $table) {
            if (Schema::hasColumn('staff_complaints', 'manager_feedback')) {
                $table->dropColumn('manager_feedback');
            }
        });
    }
};
