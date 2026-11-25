<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('daily_branch_closes')) {
            Schema::create('daily_branch_closes', function (Blueprint $table) {
                $table->id();
                $table->string('branch', 32); // old_airport | al_wakrah
                $table->date('close_date');
                $table->decimal('opening_float_qar', 10, 2)->default(0.00);
                $table->decimal('entered_cash_qar', 10, 2)->default(0.00);
                $table->decimal('entered_card_qar', 10, 2)->default(0.00);
                $table->decimal('entered_transfer_qar', 10, 2)->default(0.00);
                $table->decimal('expected_cash_qar', 10, 2)->default(0.00);
                $table->decimal('expected_card_qar', 10, 2)->default(0.00);
                $table->decimal('expected_transfer_qar', 10, 2)->default(0.00);
                $table->decimal('cash_variance_qar', 10, 2)->default(0.00);
                $table->decimal('card_variance_qar', 10, 2)->default(0.00);
                $table->decimal('transfer_variance_qar', 10, 2)->default(0.00);
                $table->decimal('total_variance_qar', 10, 2)->default(0.00);
                $table->string('status', 24)->default('balanced'); // balanced | shortage | overage
                $table->string('z_report_path')->nullable();
                $table->text('variance_note')->nullable();
                $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('submitted_at')->nullable();
                $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('verified_at')->nullable();
                $table->boolean('is_locked')->default(true);
                $table->timestamps();

                $table->unique(['branch', 'close_date'], 'daily_branch_closes_branch_date_unique');
                $table->index(['close_date', 'status'], 'daily_branch_closes_date_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_branch_closes');
    }
};
