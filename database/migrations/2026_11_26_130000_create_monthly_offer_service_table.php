<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('monthly_offer_service')) {
            Schema::create('monthly_offer_service', function (Blueprint $table) {
                $table->id();
                $table->foreignId('monthly_offer_id')->constrained('monthly_offers')->cascadeOnDelete();
                $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['monthly_offer_id', 'service_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_offer_service');
    }
};
