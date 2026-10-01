<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_run_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_run_id')->constrained('production_runs')->onDelete('cascade');
            $table->foreignId('ecommerce_raw_material_id')->constrained('ecommerce_raw_materials')->onDelete('cascade');
            $table->decimal('quantity_used', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_run_materials');
    }
};
