<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ops_action_items')) {
            return;
        }

        Schema::table('ops_action_items', function (Blueprint $table) {
            if (! Schema::hasColumn('ops_action_items', 'assigned_user_ids')) {
                $table->json('assigned_user_ids')->nullable()->after('pending_with_name');
            }
            if (! Schema::hasColumn('ops_action_items', 'assigned_names')) {
                $table->json('assigned_names')->nullable()->after('assigned_user_ids');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ops_action_items')) {
            return;
        }

        Schema::table('ops_action_items', function (Blueprint $table) {
            $dropCols = [];
            if (Schema::hasColumn('ops_action_items', 'assigned_user_ids')) {
                $dropCols[] = 'assigned_user_ids';
            }
            if (Schema::hasColumn('ops_action_items', 'assigned_names')) {
                $dropCols[] = 'assigned_names';
            }
            if (! empty($dropCols)) {
                $table->dropColumn($dropCols);
            }
        });
    }
};
