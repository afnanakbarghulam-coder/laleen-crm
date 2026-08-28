<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->onDelete('cascade');
            $table->date('deduction_date');
            $table->decimal('amount', 10, 2);
            $table->string('reason');
            // Optional link to the complaint/penalty that caused this deduction.
            $table->foreignId('complaint_id')->nullable()->constrained('staff_complaints')->onDelete('set null');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['staff_id', 'deduction_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_deductions');
    }
};
