<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecommerce_outbounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ecommerce_product_id')->constrained('ecommerce_products')->onDelete('cascade');
            $table->integer('quantity');
            $table->string('reason');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_outbounds');
    }
};
