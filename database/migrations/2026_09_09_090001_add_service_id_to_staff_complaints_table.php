<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** "Service Received" becomes a real link to the services catalog instead of free text. */
    public function up(): void
    {
        Schema::table('staff_complaints', function (Blueprint $table) {
            $table->foreignId('service_id')->nullable()->after('customer_phone')->constrained('services')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('staff_complaints', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_id');
        });
    }
};
