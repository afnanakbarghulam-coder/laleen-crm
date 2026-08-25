<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->onDelete('cascade');
            $table->foreignId('service_id')->nullable()->constrained('services')->onDelete('set null');
            $table->foreignId('staff_id')->nullable()->constrained('staff')->onDelete('set null');
            $table->string('name');
            $table->decimal('price', 10, 2)->default(0);
            $table->unsignedInteger('duration')->default(30);
            $table->dateTime('start_time');
            $table->enum('discount_type', ['flat', 'percent'])->nullable();
            $table->decimal('discount_value', 10, 2)->default(0);
            $table->timestamps();
        });

        // Backfill: split each existing appointment's flat service_name list into
        // real line items, stacked sequentially from the appointment's start time.
        $services = DB::table('services')->get(['name', 'price', 'duration'])->keyBy('name');

        DB::table('appointments')->orderBy('id')->get(['id', 'service_name', 'price', 'staff_id', 'appointment_datetime'])
            ->each(function ($appointment) use ($services) {
                $names = array_filter(array_map('trim', explode(',', $appointment->service_name)));
                if (empty($names)) {
                    return;
                }

                $cursor = \Carbon\Carbon::parse($appointment->appointment_datetime);
                $rows = [];
                $now = now();

                foreach ($names as $name) {
                    $catalog = $services->get($name);
                    $duration = $catalog->duration ?? 30;

                    $rows[] = [
                        'appointment_id' => $appointment->id,
                        'service_id' => null,
                        'staff_id' => $appointment->staff_id,
                        'name' => $name,
                        'price' => $catalog->price ?? (count($names) === 1 ? $appointment->price : 0),
                        'duration' => $duration,
                        'start_time' => $cursor->copy(),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $cursor->addMinutes($duration);
                }

                DB::table('appointment_services')->insert($rows);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_services');
    }
};
