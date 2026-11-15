<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('combos') && !Schema::hasColumn('combos', 'default_duration')) {
            Schema::table('combos', function (Blueprint $table) {
                $table->unsignedInteger('default_duration')->default(60)->after('validity_days');
            });
        }

        if (Schema::hasTable('appointments')) {
            Schema::table('appointments', function (Blueprint $table) {
                if (!Schema::hasColumn('appointments', 'combo_id')) {
                    $table->unsignedBigInteger('combo_id')->nullable()->after('service_name')->index();
                }
                if (!Schema::hasColumn('appointments', 'combo_name')) {
                    $table->string('combo_name')->nullable()->after('combo_id');
                }
            });
        }

        if (Schema::hasTable('appointment_services')) {
            Schema::table('appointment_services', function (Blueprint $table) {
                if (!Schema::hasColumn('appointment_services', 'combo_id')) {
                    $table->unsignedBigInteger('combo_id')->nullable()->after('service_id')->index();
                }
                if (!Schema::hasColumn('appointment_services', 'combo_name')) {
                    $table->string('combo_name')->nullable()->after('combo_id');
                }
                if (!Schema::hasColumn('appointment_services', 'client_package_id')) {
                    $table->unsignedBigInteger('client_package_id')->nullable()->after('combo_name')->index();
                }
                if (!Schema::hasColumn('appointment_services', 'status')) {
                    $table->string('status', 32)->default('scheduled')->after('start_time');
                }
                if (!Schema::hasColumn('appointment_services', 'is_pending')) {
                    $table->boolean('is_pending')->default(false)->after('status');
                }
            });
        }

        // Idempotent backfill for historical combo bookings & redeemed lines
        if (
            Schema::hasTable('client_package_services')
            && Schema::hasTable('client_packages')
            && Schema::hasTable('appointment_services')
            && Schema::hasTable('appointments')
        ) {
            $linkedServices = DB::table('client_package_services as cps')
                ->join('client_packages as cp', 'cp.id', '=', 'cps.client_package_id')
                ->whereNotNull('cps.appointment_service_id')
                ->select(
                    'cps.appointment_service_id',
                    'cps.status as cps_status',
                    'cp.id as client_package_id',
                    'cp.combo_id',
                    'cp.combo_name'
                )
                ->get();

            foreach ($linkedServices as $row) {
                $isPending = ($row->cps_status === 'pending');
                DB::table('appointment_services')
                    ->where('id', $row->appointment_service_id)
                    ->whereNull('combo_name')
                    ->update([
                        'combo_id' => $row->combo_id,
                        'combo_name' => $row->combo_name,
                        'client_package_id' => $row->client_package_id,
                        'status' => $isPending ? 'pending' : 'scheduled',
                        'is_pending' => $isPending,
                    ]);
            }

            $originatingPackages = DB::table('client_packages')
                ->whereNotNull('appointment_id')
                ->orderBy('id')
                ->get(['appointment_id', 'combo_id', 'combo_name']);

            foreach ($originatingPackages->groupBy('appointment_id') as $apptId => $rows) {
                $first = $rows->first();
                $joinedNames = $rows->pluck('combo_name')->filter()->unique()->implode(', ');
                if ($joinedNames !== '') {
                    DB::table('appointments')
                        ->where('id', $apptId)
                        ->whereNull('combo_name')
                        ->update([
                            'combo_id' => $first->combo_id,
                            'combo_name' => $joinedNames,
                        ]);
                }
            }

            $redeemedAppts = DB::table('appointment_services')
                ->whereNotNull('combo_name')
                ->orderBy('id')
                ->get(['appointment_id', 'combo_id', 'combo_name']);

            foreach ($redeemedAppts->groupBy('appointment_id') as $apptId => $rows) {
                $first = $rows->first();
                $joinedNames = $rows->pluck('combo_name')->filter()->unique()->implode(', ');
                if ($joinedNames !== '') {
                    DB::table('appointments')
                        ->where('id', $apptId)
                        ->whereNull('combo_name')
                        ->update([
                            'combo_id' => $first->combo_id,
                            'combo_name' => $joinedNames,
                        ]);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('appointment_services')) {
            Schema::table('appointment_services', function (Blueprint $table) {
                foreach (['is_pending', 'status', 'client_package_id', 'combo_name', 'combo_id'] as $col) {
                    if (Schema::hasColumn('appointment_services', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('appointments')) {
            Schema::table('appointments', function (Blueprint $table) {
                foreach (['combo_name', 'combo_id'] as $col) {
                    if (Schema::hasColumn('appointments', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('combos') && Schema::hasColumn('combos', 'default_duration')) {
            Schema::table('combos', function (Blueprint $table) {
                $table->dropColumn('default_duration');
            });
        }
    }
};
