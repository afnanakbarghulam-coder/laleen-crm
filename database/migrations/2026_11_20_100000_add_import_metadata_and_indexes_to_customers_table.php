<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('customers')) {
            return;
        }

        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'status')) {
                $col = $table->string('status', 32)->default('active')->index();
                if (Schema::hasColumn('customers', 'branch')) {
                    $col->after('branch');
                }
            }

            if (!Schema::hasColumn('customers', 'import_source')) {
                $col = $table->string('import_source', 64)->nullable()->index();
                if (Schema::hasColumn('customers', 'status')) {
                    $col->after('status');
                }
            }
        });

        // Ensure alphabetical pagination & lookup indexes exist for 11,000+ client scale.
        if (!Schema::hasIndex('customers', 'customers_name_index')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->index('name', 'customers_name_index');
            });
        }

        if (Schema::hasColumn('customers', 'branch') && !Schema::hasIndex('customers', 'customers_branch_index')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->index('branch', 'customers_branch_index');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('customers')) {
            return;
        }

        if (Schema::hasIndex('customers', 'customers_name_index')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropIndex('customers_name_index');
            });
        }

        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'import_source')) {
                $table->dropIndex(['import_source']);
                $table->dropColumn('import_source');
            }
            if (Schema::hasColumn('customers', 'status')) {
                $table->dropIndex(['status']);
                $table->dropColumn('status');
            }
        });
    }
};
