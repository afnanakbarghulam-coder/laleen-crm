<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->date('booking_date')->nullable()->after('appointment_datetime');
            $table->time('start_time')->nullable()->after('booking_date');
        });

        // Backfill existing appointments from appointment_datetime
        DB::table('appointments')->orderBy('id')->get(['id', 'appointment_datetime'])->each(function ($row) {
            if ($row->appointment_datetime) {
                $dt = \Illuminate\Support\Carbon::parse($row->appointment_datetime);
                DB::table('appointments')->where('id', $row->id)->update([
                    'booking_date' => $dt->format('Y-m-d'),
                    'start_time' => $dt->format('H:i:s'),
                ]);
            }
        });

        Schema::table('appointment_services', function (Blueprint $table) {
            $table->dateTime('start_time')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['booking_date', 'start_time']);
        });

        Schema::table('appointment_services', function (Blueprint $table) {
            $table->dateTime('start_time')->nullable(false)->change();
        });
    }
};
