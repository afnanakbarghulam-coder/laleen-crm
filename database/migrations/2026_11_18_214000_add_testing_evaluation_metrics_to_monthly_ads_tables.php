<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['monthly_ads', 'ecommerce_monthly_ads'] as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'aggressive_reaction')) {
                    $table->string('aggressive_reaction', 16)->nullable();
                }
                if (!Schema::hasColumn($tableName, 'cpm')) {
                    $table->decimal('cpm', 10, 2)->nullable();
                }
                if (!Schema::hasColumn($tableName, 'ctr')) {
                    $table->decimal('ctr', 6, 2)->nullable();
                }
                if (!Schema::hasColumn($tableName, 'lead_conversion_status')) {
                    $table->string('lead_conversion_status', 16)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive in production environments
    }
};
