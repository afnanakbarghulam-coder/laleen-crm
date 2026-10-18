<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecommerce_customers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('phone', 40)->unique();
            $table->string('email')->nullable();
            $table->decimal('total_spend', 14, 2)->default(0);
            $table->unsignedInteger('total_orders')->default(0);
            $table->timestamp('last_purchase_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_customers');
    }
};
