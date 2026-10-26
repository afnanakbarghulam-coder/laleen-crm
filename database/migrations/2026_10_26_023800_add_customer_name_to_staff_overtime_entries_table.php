<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_overtime_entries', function (Blueprint $table) {
            if (!Schema::hasColumn('staff_overtime_entries', 'customer_name')) {
                $table->string('customer_name')->nullable()->after('rate');
            }
        });
    }

    public function down(): void
    {
        Schema::table('staff_overtime_entries', function (Blueprint $table) {
            if (Schema::hasColumn('staff_overtime_entries', 'customer_name')) {
                $table->dropColumn('customer_name');
            }
        });
    }
};
