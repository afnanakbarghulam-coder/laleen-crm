<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->json('service_interest_json')->nullable()->after('service_interest');
        });

        DB::table('leads')
            ->whereNotNull('service_interest')
            ->where('service_interest', '!=', '')
            ->get(['id', 'service_interest'])
            ->each(function ($lead) {
                DB::table('leads')->where('id', $lead->id)->update([
                    'service_interest_json' => json_encode([$lead->service_interest]),
                ]);
            });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('service_interest');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->renameColumn('service_interest_json', 'service_interest');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('service_interest_string')->nullable()->after('service_interest');
        });

        DB::table('leads')
            ->whereNotNull('service_interest')
            ->get(['id', 'service_interest'])
            ->each(function ($lead) {
                $decoded = json_decode($lead->service_interest, true);
                $value = is_array($decoded) ? ($decoded[0] ?? null) : $lead->service_interest;

                DB::table('leads')->where('id', $lead->id)->update([
                    'service_interest_string' => $value,
                ]);
            });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('service_interest');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->renameColumn('service_interest_string', 'service_interest');
        });
    }
};
