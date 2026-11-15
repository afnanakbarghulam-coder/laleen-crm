<?php

use App\Models\Customer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customers') && !Schema::hasColumn('customers', 'branch')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('branch', 60)->nullable()->index()->after('email');
            });
        }

        if (Schema::hasTable('leads') && !Schema::hasColumn('leads', 'branch')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->string('branch', 60)->nullable()->index()->after('source');
            });
        }

        Customer::backfillBranches();
    }

    public function down(): void
    {
        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'branch')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropIndex(['branch']);
                $table->dropColumn('branch');
            });
        }

        if (Schema::hasTable('leads') && Schema::hasColumn('leads', 'branch')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropIndex(['branch']);
                $table->dropColumn('branch');
            });
        }
    }
};
