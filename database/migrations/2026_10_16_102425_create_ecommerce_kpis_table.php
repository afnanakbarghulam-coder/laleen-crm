<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ecommerce_kpis')) {
            return;
        }

        Schema::create('ecommerce_kpis', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->decimal('total_revenue', 12, 2)->default(0);
            $table->decimal('total_ad_spend', 12, 2)->default(0);
            $table->integer('total_orders')->default(0);
            $table->integer('new_customers')->default(0);
            $table->integer('repeat_customers')->default(0);
            $table->timestamps();

            // One blended snapshot per day — re-submitting a date updates it
            // rather than silently double-counting revenue and ad spend.
            $table->unique('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_kpis');
    }
};
