<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecommerce_expenses', function (Blueprint $table) {
            $table->dropIndex('ecommerce_expenses_category_index');
            $table->dropColumn('category');
        });

        Schema::table('ecommerce_expenses', function (Blueprint $table) {
            $table->string('category', 100)->after('amount');
            $table->text('notes')->nullable()->after('vendor');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::table('ecommerce_expenses', function (Blueprint $table) {
            $table->dropIndex('ecommerce_expenses_category_index');
            $table->dropColumn(['category', 'notes']);
        });

        Schema::table('ecommerce_expenses', function (Blueprint $table) {
            $table->enum('category', [
                'Paid Traffic & Ads',
                'Creative & Content',
                'Logistics & Fulfillment',
                'Platform & Software',
                'R&D & Compliance',
            ])->after('amount');
        });
    }
};
