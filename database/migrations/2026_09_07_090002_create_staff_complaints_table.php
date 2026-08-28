<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->onDelete('cascade');
            $table->date('complaint_date');
            $table->string('category');
            $table->text('description');
            $table->enum('severity', ['Low', 'Medium', 'High'])->default('Low');
            $table->enum('status', ['Open', 'Resolved'])->default('Open');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['staff_id', 'complaint_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_complaints');
    }
};
