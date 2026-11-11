<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sales') || !Schema::hasColumn('sales', 'appointment_id')) {
            return;
        }

        // Safely detach any legacy accidental duplicate non-null appointment_id rows
        // (keeping the earliest sale linked) so adding the unique constraint never fails.
        $duplicateAppointmentIds = DB::table('sales')
            ->whereNotNull('appointment_id')
            ->select('appointment_id')
            ->groupBy('appointment_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('appointment_id');

        foreach ($duplicateAppointmentIds as $appointmentId) {
            $saleIds = DB::table('sales')
                ->where('appointment_id', $appointmentId)
                ->orderBy('id')
                ->pluck('id');

            $duplicateIds = $saleIds->slice(1)->values()->all();
            if (!empty($duplicateIds)) {
                DB::table('sales')
                    ->whereIn('id', $duplicateIds)
                    ->update(['appointment_id' => null]);
            }
        }

        if (!Schema::hasIndex('sales', 'sales_appointment_id_unique')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->unique('appointment_id', 'sales_appointment_id_unique');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('sales')) {
            return;
        }

        if (Schema::hasIndex('sales', 'sales_appointment_id_unique')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropUnique('sales_appointment_id_unique');
            });
        }
    }
};
