<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->renameColumn('notes', 'customer_remarks');
            $table->renameColumn('followup_date', 'next_followup_date');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['name', 'lead_source', 'status']);

            $table->string('category')->nullable()->after('assigned_agent_id');
            $table->string('service_interest')->nullable()->after('customer_remarks');
            $table->string('booking_status')->nullable()->after('service_interest');
            $table->string('correction_done')->nullable()->after('booking_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['category', 'service_interest', 'booking_status', 'correction_done']);

            $table->string('name')->nullable();
            $table->string('lead_source')->nullable();
            $table->string('status')->default('pending');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->renameColumn('customer_remarks', 'notes');
            $table->renameColumn('next_followup_date', 'followup_date');
        });
    }
};
