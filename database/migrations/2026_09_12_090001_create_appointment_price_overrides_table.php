<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only audit trail: one row per manual price override/discount
     * event on a booking's service line, kept even if the line item or the
     * discount on it is later changed again - so "who discounted what, by
     * how much, and why" stays reportable independent of current state.
     */
    public function up(): void
    {
        Schema::create('appointment_price_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->onDelete('cascade');
            $table->foreignId('appointment_service_id')->nullable()->constrained('appointment_services')->onDelete('set null');
            $table->string('service_name');
            $table->decimal('original_price', 10, 2);
            $table->decimal('new_price', 10, 2);
            $table->decimal('discount_amount', 10, 2);
            $table->string('discount_reason')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_price_overrides');
    }
};
