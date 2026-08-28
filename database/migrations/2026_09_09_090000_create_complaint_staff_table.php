<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivot for "Staff Involved" — a complaint can now name multiple staff
     * members rather than exactly one.
     */
    public function up(): void
    {
        Schema::create('complaint_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_complaint_id')->constrained('staff_complaints')->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['staff_complaint_id', 'staff_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaint_staff');
    }
};
