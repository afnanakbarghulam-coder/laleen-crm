<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('phone')->unique();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Backfill: one customer per distinct phone already present in appointments,
        // using their most recent booking's name as the display name.
        if (Schema::hasTable('appointments')) {
            $rows = DB::table('appointments')
                ->select('phone', 'customer_name', 'created_at')
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->orderByDesc('created_at')
                ->get()
                ->groupBy('phone');

            $now = now();
            $inserts = [];

            foreach ($rows as $phone => $group) {
                $inserts[] = [
                    'name'       => $group->first()->customer_name,
                    'phone'      => $phone,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($inserts, 200) as $chunk) {
                DB::table('customers')->insert($chunk);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
