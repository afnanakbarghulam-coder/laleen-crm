<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('staff_complaints') && !Schema::hasColumn('staff_complaints', 'resolved_at')) {
            Schema::table('staff_complaints', function (Blueprint $table) {
                $table->timestamp('resolved_at')->nullable()->after('is_staff_mistake');
            });
        }

        if (Schema::hasTable('leads') && !Schema::hasColumn('leads', 'source')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->string('source', 100)->nullable()->after('category');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('staff_complaints') && Schema::hasColumn('staff_complaints', 'resolved_at')) {
            Schema::table('staff_complaints', function (Blueprint $table) {
                $table->dropColumn('resolved_at');
            });
        }

        if (Schema::hasTable('leads') && Schema::hasColumn('leads', 'source')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropColumn('source');
            });
        }
    }
};
