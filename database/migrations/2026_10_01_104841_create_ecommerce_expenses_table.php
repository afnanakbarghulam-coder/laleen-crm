<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecommerce_expenses', function (Blueprint $table) {
            $table->id();
            $table->date('expense_date');
            $table->string('title');
            $table->decimal('amount', 10, 2);
            $table->enum('category', [
                'Paid Traffic & Ads',
                'Creative & Content',
                'Logistics & Fulfillment',
                'Platform & Software',
                'R&D & Compliance',
            ]);
            $table->string('vendor')->nullable();
            $table->string('receipt_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index('expense_date');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_expenses');
    }
};
