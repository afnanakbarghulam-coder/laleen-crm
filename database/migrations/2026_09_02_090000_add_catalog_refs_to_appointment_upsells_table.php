<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Upsells are now picked from the real Services/Products catalog rather
     * than free-typed, and need to flow into checkout as proper sale line
     * items — so they need the same type/catalog-reference shape as
     * sale_items (see 2026_08_25_100004_create_sale_items_table.php).
     */
    public function up(): void
    {
        Schema::table('appointment_upsells', function (Blueprint $table) {
            $table->enum('type', ['service', 'product'])->default('service')->after('appointment_id');
            $table->foreignId('service_id')->nullable()->after('type')->constrained('services')->onDelete('set null');
            $table->foreignId('product_id')->nullable()->after('service_id')->constrained('products')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('appointment_upsells', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_id');
            $table->dropConstrainedForeignId('product_id');
            $table->dropColumn('type');
        });
    }
};
