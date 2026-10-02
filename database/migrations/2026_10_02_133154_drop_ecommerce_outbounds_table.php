<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('ecommerce_outbounds');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('ecommerce_outbounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ecommerce_product_id')->constrained('ecommerce_products')->onDelete('cascade');
            $table->string('customer_name')->nullable();
            $table->string('contact_number')->nullable();
            $table->integer('quantity');
            $table->decimal('price', 10, 2)->nullable();
            $table->string('reason');
            $table->timestamps();
        });
    }
};
