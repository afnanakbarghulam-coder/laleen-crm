<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
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
            $table->string('staff_name');
            $table->decimal('total_upsell', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_staff_sales_entries');
        Schema::dropIfExists('kpi_staff_sales_reports');
    }
};
