<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('monthly_offers')) {
            Schema::create('monthly_offers', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('offer_type', 32)->default('standalone'); // combo | service_freebie | standalone
                $table->foreignId('combo_id')->nullable()->constrained('combos')->nullOnDelete();
                $table->foreignId('service_freebie_id')->nullable()->constrained('service_freebie_packages')->nullOnDelete();
                $table->decimal('promo_price_qar', 10, 2)->nullable();
                $table->decimal('original_value_qar', 10, 2)->nullable();
                $table->string('branch_scope', 32)->default('all'); // all | old_airport | al_wakrah
                $table->date('valid_from');
                $table->date('valid_until');
                $table->text('terms_and_conditions');
                $table->text('staff_notes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['is_active', 'valid_from', 'valid_until'], 'monthly_offers_active_dates_idx');
                $table->index(['branch_scope', 'offer_type'], 'monthly_offers_scope_type_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_offers');
    }
};
