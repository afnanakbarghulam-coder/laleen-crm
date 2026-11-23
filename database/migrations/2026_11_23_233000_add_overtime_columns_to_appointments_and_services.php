<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('appointments')) {
            Schema::table('appointments', function (Blueprint $table) {
                if (! Schema::hasColumn('appointments', 'is_overtime')) {
                    $table->boolean('is_overtime')->default(false)->after('hold_duration')->index();
                }
                if (! Schema::hasColumn('appointments', 'overtime_minutes')) {
                    $table->unsignedInteger('overtime_minutes')->default(0)->after('is_overtime');
                }
            });
        }

        if (Schema::hasTable('appointment_services')) {
            Schema::table('appointment_services', function (Blueprint $table) {
                if (! Schema::hasColumn('appointment_services', 'is_overtime')) {
                    $table->boolean('is_overtime')->default(false)->after('slice_end_at')->index();
                }
                if (! Schema::hasColumn('appointment_services', 'overtime_minutes')) {
                    $table->unsignedInteger('overtime_minutes')->default(0)->after('is_overtime');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('appointment_services')) {
            Schema::table('appointment_services', function (Blueprint $table) {
                if (Schema::hasColumn('appointment_services', 'overtime_minutes')) {
                    $table->dropColumn('overtime_minutes');
                }
                if (Schema::hasColumn('appointment_services', 'is_overtime')) {
                    $table->dropIndex(['is_overtime']);
                    $table->dropColumn('is_overtime');
                }
            });
        }

        if (Schema::hasTable('appointments')) {
            Schema::table('appointments', function (Blueprint $table) {
                if (Schema::hasColumn('appointments', 'overtime_minutes')) {
                    $table->dropColumn('overtime_minutes');
                }
                if (Schema::hasColumn('appointments', 'is_overtime')) {
                    $table->dropIndex(['is_overtime']);
                    $table->dropColumn('is_overtime');
                }
            });
        }
    }
};
