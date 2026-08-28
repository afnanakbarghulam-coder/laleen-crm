<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Staff Sales entries are now generated automatically per active staff
     * member, sourced from appointment_upsells — staff_id links each entry
     * back to the real Staff record instead of relying on the free-typed
     * staff_name alone.
     */
    public function up(): void
    {
        Schema::table('kpi_staff_sales_entries', function (Blueprint $table) {
            $table->foreignId('staff_id')->nullable()->after('report_id')->constrained('staff')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('kpi_staff_sales_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('staff_id');
        });
    }
};
