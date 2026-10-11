<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kpi_staff_evaluations', function (Blueprint $table) {
            $table->enum('branch', ['old_airport', 'wakrah'])->after('evaluator_id');

            $table->index(['branch', 'evaluation_date']);
        });
    }

    public function down(): void
    {
        Schema::table('kpi_staff_evaluations', function (Blueprint $table) {
            $table->dropIndex(['branch', 'evaluation_date']);
            $table->dropColumn('branch');
        });
    }
};
