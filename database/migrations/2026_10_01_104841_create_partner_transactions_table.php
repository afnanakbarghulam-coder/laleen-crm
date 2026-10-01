<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('partners')->onDelete('cascade');
            $table->enum('type', ['injection', 'distribution']);
            $table->decimal('amount', 10, 2);
            $table->string('category')->nullable();
            $table->string('reference_note')->nullable();
            $table->date('transaction_date');
            $table->timestamps();

            $table->index('transaction_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_transactions');
    }
};
