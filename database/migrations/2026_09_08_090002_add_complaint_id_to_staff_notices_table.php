<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets a notice be traced back to the complaint that prompted it (the
     * "Generate Staff Notice" action on a Complaints & Feedback row).
     */
    public function up(): void
    {
        Schema::table('staff_notices', function (Blueprint $table) {
            $table->foreignId('complaint_id')->nullable()->after('staff_id')
                ->constrained('staff_complaints')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('staff_notices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('complaint_id');
        });
    }
};
