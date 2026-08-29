<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Beauty Planning schedule query joins appointment_services -> appointments
 * -> services and aggregates MAX(start_time) per (customer_id, service_id).
 * service_id and customer_id already carry an index from their foreign key
 * constraints; start_time and status did not, so the engine had to scan every
 * appointment_services/appointments row for that filter. These composite
 * indexes let it seek straight to the relevant rows instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointment_services', function (Blueprint $table) {
            $table->index(['service_id', 'start_time'], 'appt_services_service_start_idx');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->index(['customer_id', 'status'], 'appointments_customer_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('appointment_services', function (Blueprint $table) {
            $table->dropIndex('appt_services_service_start_idx');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('appointments_customer_status_idx');
        });
    }
};
