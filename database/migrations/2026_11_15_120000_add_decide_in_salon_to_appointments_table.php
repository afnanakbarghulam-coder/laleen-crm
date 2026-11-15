<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('appointments')) {
            return;
        }

        Schema::table('appointments', function (Blueprint $table) {
            if (!Schema::hasColumn('appointments', 'decide_in_salon')) {
                $table->boolean('decide_in_salon')->default(false)->after('combo_name');
            }
            if (!Schema::hasColumn('appointments', 'hold_duration')) {
                $table->unsignedInteger('hold_duration')->nullable()->after('decide_in_salon');
            }
        });

        // Backfill standalone legacy "Decide in Salon" placeholder appointments
        if (Schema::hasColumn('appointments', 'decide_in_salon')) {
            $query = DB::table('appointments')
                ->whereNull('combo_id')
                ->where(function ($q) {
                    $q->whereNull('combo_name')->orWhere('combo_name', '');
                })
                ->whereRaw('LOWER(TRIM(COALESCE(service_name, \'\'))) = ?', ['decide in salon']);

            if (Schema::hasTable('appointment_services')) {
                $query->whereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('appointment_services')
                        ->whereColumn('appointment_services.appointment_id', 'appointments.id');
                });
            }

            if (Schema::hasTable('client_packages')) {
                $query->whereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('client_packages')
                        ->whereColumn('client_packages.appointment_id', 'appointments.id');
                });
            }

            $query->update([
                'decide_in_salon' => true,
                'hold_duration' => 60,
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('appointments')) {
            return;
        }

        Schema::table('appointments', function (Blueprint $table) {
            if (Schema::hasColumn('appointments', 'hold_duration')) {
                $table->dropColumn('hold_duration');
            }
            if (Schema::hasColumn('appointments', 'decide_in_salon')) {
                $table->dropColumn('decide_in_salon');
            }
        });
    }
};
