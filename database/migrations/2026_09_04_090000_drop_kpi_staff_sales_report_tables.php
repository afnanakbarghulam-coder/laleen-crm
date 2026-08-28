<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Staff Sales Performance no longer generates or saves reports — the
     * "Sales & Upsells" and "Analytics" tabs compute everything live from
     * appointment_upsells (see App\Support\StaffSalesAnalytics). These
     * tables have no remaining readers or writers.
     */
    public function up(): void
    {
        Schema::dropIfExists('kpi_staff_sales_entries');
        Schema::dropIfExists('kpi_staff_sales_reports');
    }

    public function down(): void
    {
        Schema::create('kpi_staff_sales_reports', function (Blueprint $table) {
            $table->id();
            $table->enum('branch', ['old_airport', 'wakrah']);
            $table->date('date_from');
            $table->date('date_to');
            $table->decimal('monthly_target_per_staff', 10, 2)->default(1700);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });

        Schema::create('kpi_staff_sales_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('kpi_staff_sales_reports')->onDelete('cascade');
            $table->foreignId('staff_id')->nullable()->constrained('staff')->onDelete('set null');
            $table->string('staff_name');
            $table->decimal('total_upsell', 10, 2)->default(0);
            $table->timestamps();
        });
    }
};
