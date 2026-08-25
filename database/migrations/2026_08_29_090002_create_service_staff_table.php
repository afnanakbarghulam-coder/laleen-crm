<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->onDelete('cascade');
            $table->foreignId('staff_id')->constrained('staff')->onDelete('cascade');
            $table->timestamps();
            $table->unique(['service_id', 'staff_id']);
        });

        // Backfill: derive initial team-member eligibility from each staff
        // member's free-text `skills` list, matching the same loose
        // substring logic the booking flow used before this pivot existed.
        $services = DB::table('services')->get(['id', 'name']);
        $staffRows = DB::table('staff')->get(['id', 'skills']);
        $now = now();
        $rows = [];

        foreach ($staffRows as $staffRow) {
            $skills = json_decode($staffRow->skills ?? '[]', true) ?: [];
            $skills = array_map(fn($s) => strtolower(trim($s)), $skills);

            foreach ($services as $service) {
                $name = strtolower($service->name);
                $matches = collect($skills)->contains(
                    fn($skill) => $skill !== '' && (str_contains($name, $skill) || str_contains($skill, $name))
                );

                if ($matches) {
                    $rows[] = [
                        'service_id' => $service->id,
                        'staff_id' => $staffRow->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('service_staff')->insert($chunk);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('service_staff');
    }
};
