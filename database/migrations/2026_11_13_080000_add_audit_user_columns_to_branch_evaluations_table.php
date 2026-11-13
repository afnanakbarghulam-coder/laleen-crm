<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('branch_evaluations')) {
            return;
        }

        Schema::table('branch_evaluations', function (Blueprint $table) {
            if (!Schema::hasColumn('branch_evaluations', 'created_by')) {
                $table->foreignId('created_by')
                    ->nullable()
                    ->after('action_plan')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('branch_evaluations', 'updated_by')) {
                $table->foreignId('updated_by')
                    ->nullable()
                    ->after('created_by')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('branch_evaluations')) {
            return;
        }

        Schema::table('branch_evaluations', function (Blueprint $table) {
            if (Schema::hasColumn('branch_evaluations', 'updated_by')) {
                $table->dropConstrainedForeignId('updated_by');
            }
            if (Schema::hasColumn('branch_evaluations', 'created_by')) {
                $table->dropConstrainedForeignId('created_by');
            }
        });
    }
};
