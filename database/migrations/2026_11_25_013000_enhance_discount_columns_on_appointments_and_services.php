<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('appointment_services')) {
            Schema::table('appointment_services', function (Blueprint $table) {
                if (! Schema::hasColumn('appointment_services', 'original_price')) {
                    $table->decimal('original_price', 10, 2)->nullable()->after('price');
                }
                if (! Schema::hasColumn('appointment_services', 'discount_type')) {
                    $table->string('discount_type', 20)->nullable()->after('start_time');
                } else {
                    // Widen legacy enum('flat', 'percent') to nullable string so 'fixed', 'flat', and 'percent' persist cleanly.
                    $table->string('discount_type', 20)->nullable()->change();
                }
                if (! Schema::hasColumn('appointment_services', 'discount_value')) {
                    $table->decimal('discount_value', 10, 2)->default(0)->after('discount_type');
                }
                if (! Schema::hasColumn('appointment_services', 'discount_amount')) {
                    $table->decimal('discount_amount', 10, 2)->default(0)->after('discount_value');
                }
                if (! Schema::hasColumn('appointment_services', 'discount_reason')) {
                    $table->string('discount_reason', 255)->nullable()->after('discount_amount');
                }
            });

            DB::table('appointment_services')
                ->whereNull('original_price')
                ->update(['original_price' => DB::raw('price')]);
        }

        if (Schema::hasTable('appointments')) {
            Schema::table('appointments', function (Blueprint $table) {
                if (! Schema::hasColumn('appointments', 'original_price')) {
                    $table->decimal('original_price', 10, 2)->nullable()->after('price');
                }
                if (! Schema::hasColumn('appointments', 'discount_amount')) {
                    $table->decimal('discount_amount', 10, 2)->default(0)->after('original_price');
                }
            });

            DB::table('appointments')
                ->whereNull('original_price')
                ->update(['original_price' => DB::raw('price')]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('appointments')) {
            Schema::table('appointments', function (Blueprint $table) {
                if (Schema::hasColumn('appointments', 'discount_amount')) {
                    $table->dropColumn('discount_amount');
                }
                if (Schema::hasColumn('appointments', 'original_price')) {
                    $table->dropColumn('original_price');
                }
            });
        }
    }
};
