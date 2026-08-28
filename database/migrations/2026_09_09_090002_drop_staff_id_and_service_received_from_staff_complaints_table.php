<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Staff Involved" moved to the complaint_staff pivot (multi-select);
     * "Service Received" moved to service_id (dropdown from the services
     * catalog). Nothing has been logged against either old column yet.
     */
    public function up(): void
    {
        Schema::table('staff_complaints', function (Blueprint $table) {
            $table->dropIndex(['staff_id', 'complaint_date']);
            $table->dropConstrainedForeignId('staff_id');
            $table->dropColumn('service_received');
        });
    }

    public function down(): void
    {
        Schema::table('staff_complaints', function (Blueprint $table) {
            $table->foreignId('staff_id')->nullable()->constrained('staff')->cascadeOnDelete();
            $table->string('service_received')->nullable();
            $table->index(['staff_id', 'complaint_date']);
        });
    }
};
