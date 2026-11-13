<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('staff_contract_categories')) {
            Schema::create('staff_contract_categories', function (Blueprint $table) {
                $table->id();
                $table->string('slug', 80)->unique();
                $table->string('label', 120);
                $table->string('default_currency', 8)->default('QAR');
                $table->string('default_branch', 32)->default('old_airport');
                $table->longText('clauses');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_contract_categories');
    }
};
