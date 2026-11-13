<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('client_packages')) {
            Schema::table('client_packages', function (Blueprint $table) {
                if (!Schema::hasColumn('client_packages', 'validity_days')) {
                    $table->unsignedInteger('validity_days')->nullable()->after('quantity_included');
                }
                $table->dateTime('purchased_at')->nullable()->change();
                $table->dateTime('expires_at')->nullable()->change();
            });
        }

        if (Schema::hasTable('client_package_services') && !Schema::hasColumn('client_package_services', 'sale_id')) {
            Schema::table('client_package_services', function (Blueprint $table) {
                $table->foreignId('sale_id')->nullable()->after('appointment_service_id')->constrained('sales')->nullOnDelete();
            });
        }

        if (Schema::hasTable('sale_items') && !Schema::hasColumn('sale_items', 'client_package_service_id')) {
            Schema::table('sale_items', function (Blueprint $table) {
                $table->foreignId('client_package_service_id')
                    ->nullable()
                    ->after('product_id')
                    ->constrained('client_package_services')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sale_items') && Schema::hasColumn('sale_items', 'client_package_service_id')) {
            Schema::table('sale_items', function (Blueprint $table) {
                $table->dropConstrainedForeignId('client_package_service_id');
            });
        }

        if (Schema::hasTable('client_package_services') && Schema::hasColumn('client_package_services', 'sale_id')) {
            Schema::table('client_package_services', function (Blueprint $table) {
                $table->dropConstrainedForeignId('sale_id');
            });
        }

        if (Schema::hasTable('client_packages') && Schema::hasColumn('client_packages', 'validity_days')) {
            Schema::table('client_packages', function (Blueprint $table) {
                $table->dropColumn('validity_days');
            });
        }
    }
};
