<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_ads_conversion_reports', function (Blueprint $table) {
            $table->id();
            $table->date('date_from');
            $table->date('date_to');
            $table->json('categories');
            $table->unsignedInteger('old_airport_bookings')->default(0);
            $table->decimal('old_airport_revenue', 10, 2)->default(0);
            $table->unsignedInteger('wakrah_bookings')->default(0);
            $table->decimal('wakrah_revenue', 10, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_ads_conversion_reports');
    }
};
