<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('customers', 'last_retention_contacted_at')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->timestamp('last_retention_contacted_at')->nullable()->after('loyalty_points');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('customers', 'last_retention_contacted_at')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('last_retention_contacted_at');
            });
        }
    }
};
