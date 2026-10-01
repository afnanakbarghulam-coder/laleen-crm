<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecommerce_expenses', function (Blueprint $table) {
            $table->enum('funding_source', ['partner_ledger', 'sales'])
                ->default('partner_ledger')
                ->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('ecommerce_expenses', function (Blueprint $table) {
            $table->dropColumn('funding_source');
        });
    }
};
