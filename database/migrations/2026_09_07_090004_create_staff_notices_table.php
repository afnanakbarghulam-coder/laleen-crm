<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->onDelete('cascade');
            $table->date('notice_date');
            $table->enum('type', ['Verbal Warning', 'Written Warning', 'Final Notice', 'Termination Notice'])->default('Verbal Warning');
            $table->string('subject');
            $table->text('description')->nullable();
            $table->enum('acknowledged', ['Y', 'N'])->default('N');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['staff_id', 'notice_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_notices');
    }
};
