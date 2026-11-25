<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('client_packages')) {
            Schema::table('client_packages', function (Blueprint $table) {
                if (! Schema::hasColumn('client_packages', 'is_custom_combo')) {
                    $table->boolean('is_custom_combo')->default(false)->after('combo_name');
                }
                if (! Schema::hasColumn('client_packages', 'original_combo_price')) {
                    $table->decimal('original_combo_price', 10, 2)->nullable()->after('price_paid');
                }
                if (! Schema::hasColumn('client_packages', 'combo_override_reason')) {
                    $table->string('combo_override_reason', 150)->nullable()->after('original_combo_price');
                }
            });

            DB::table('client_packages')
                ->whereNull('original_combo_price')
                ->update(['original_combo_price' => DB::raw('price_paid')]);
        }

        if (Schema::hasTable('appointment_services')) {
            Schema::table('appointment_services', function (Blueprint $table) {
                if (! Schema::hasColumn('appointment_services', 'is_combo_extra')) {
                    $table->boolean('is_combo_extra')->default(false)->after('combo_name');
                }
            });
        }

        if (Schema::hasTable('appointments')) {
            Schema::table('appointments', function (Blueprint $table) {
                if (! Schema::hasColumn('appointments', 'is_custom_combo')) {
                    $table->boolean('is_custom_combo')->default(false)->after('combo_name');
                }
                if (! Schema::hasColumn('appointments', 'original_combo_price')) {
                    $table->decimal('original_combo_price', 10, 2)->nullable()->after('is_custom_combo');
                }
                if (! Schema::hasColumn('appointments', 'combo_override_reason')) {
                    $table->string('combo_override_reason', 150)->nullable()->after('original_combo_price');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('appointments')) {
            Schema::table('appointments', function (Blueprint $table) {
                foreach (['combo_override_reason', 'original_combo_price', 'is_custom_combo'] as $col) {
                    if (Schema::hasColumn('appointments', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('appointment_services') && Schema::hasColumn('appointment_services', 'is_combo_extra')) {
            Schema::table('appointment_services', function (Blueprint $table) {
                $table->dropColumn('is_combo_extra');
            });
        }

        if (Schema::hasTable('client_packages')) {
            Schema::table('client_packages', function (Blueprint $table) {
                foreach (['combo_override_reason', 'original_combo_price', 'is_custom_combo'] as $col) {
                    if (Schema::hasColumn('client_packages', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
