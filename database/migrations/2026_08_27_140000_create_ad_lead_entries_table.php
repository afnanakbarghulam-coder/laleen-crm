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
        Schema::create('ad_lead_entries', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('phone');
            $table->string('category');
            $table->decimal('ticket_amount', 10, 2)->default(0);
            $table->enum('branch', ['old_airport', 'wakrah'])->nullable();
            $table->string('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['date', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ad_lead_entries');
    }
};
