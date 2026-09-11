<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per combo a customer has bought. Pricing/quantity are snapshot
     * at purchase time (like original_price elsewhere) so later edits to the
     * Combo catalog row never change what a client already paid for.
     */
    public function up(): void
    {
        Schema::create('client_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('combo_id')->constrained('combos');
            $table->foreignId('sale_id')->nullable()->constrained('sales')->onDelete('set null');
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->onDelete('set null');
            $table->string('combo_name');
            $table->decimal('price_paid', 10, 2);
            $table->unsignedInteger('quantity_included');
            $table->dateTime('purchased_at');
            $table->dateTime('expires_at');
            $table->string('status', 20)->default('active'); // active, completed, expired
            $table->timestamps();

            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_packages');
    }
};
