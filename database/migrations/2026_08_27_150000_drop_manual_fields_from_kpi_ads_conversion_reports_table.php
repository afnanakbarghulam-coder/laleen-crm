<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ads Conversion Reports are now fully derived from ad_lead_entries for
     * their date range (see KpiAdsConversionReport::computedCategories() and
     * the branch total accessors) — these manually-entered columns are no
     * longer written to or read from.
     */
    public function up(): void
    {
        Schema::table('kpi_ads_conversion_reports', function (Blueprint $table) {
            $table->dropColumn([
                'categories',
                'old_airport_bookings',
                'old_airport_revenue',
                'wakrah_bookings',
                'wakrah_revenue',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('kpi_ads_conversion_reports', function (Blueprint $table) {
            $table->json('categories')->nullable();
            $table->unsignedInteger('old_airport_bookings')->default(0);
            $table->decimal('old_airport_revenue', 10, 2)->default(0);
            $table->unsignedInteger('wakrah_bookings')->default(0);
            $table->decimal('wakrah_revenue', 10, 2)->default(0);
        });
    }
};
