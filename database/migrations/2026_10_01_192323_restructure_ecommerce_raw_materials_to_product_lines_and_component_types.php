<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ecommerce_raw_materials', function (Blueprint $table) {
            $table->foreignId('product_line_id')->nullable()->after('name')->constrained('ecommerce_product_lines')->nullOnDelete();
            $table->foreignId('component_type_id')->nullable()->after('product_line_id')->constrained('ecommerce_component_types')->nullOnDelete();
        });

        Schema::table('ecommerce_raw_materials', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ecommerce_raw_materials', function (Blueprint $table) {
            $table->string('type')->nullable();
        });

        Schema::table('ecommerce_raw_materials', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_line_id');
            $table->dropConstrainedForeignId('component_type_id');
        });
    }
};
