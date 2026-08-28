<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_overtime_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->onDelete('cascade');
            $table->date('entry_date');
            $table->decimal('hours', 5, 2);
            // Per-entry rate override (QAR/hour); falls back to staff.hourly_wage
            // at calculation time when left blank.
            $table->decimal('rate', 10, 2)->nullable();
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['staff_id', 'entry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_overtime_entries');
    }
};
