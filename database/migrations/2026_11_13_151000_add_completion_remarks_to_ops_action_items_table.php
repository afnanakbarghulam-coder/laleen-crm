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
            if (! Schema::hasColumn('ops_action_items', 'completion_remarks')) {
                $table->text('completion_remarks')->nullable()->after('proof_note');
            }

            if (! Schema::hasColumn('ops_action_items', 'verified_by_user_id')) {
                $table->unsignedBigInteger('verified_by_user_id')->nullable()->after('completed_by');
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
            if (Schema::hasColumn('ops_action_items', 'completion_remarks')) {
                $dropCols[] = 'completion_remarks';
            }
            if (Schema::hasColumn('ops_action_items', 'verified_by_user_id')) {
                $dropCols[] = 'verified_by_user_id';
            }
            if (! empty($dropCols)) {
                $table->dropColumn($dropCols);
            }
        });
    }
};
