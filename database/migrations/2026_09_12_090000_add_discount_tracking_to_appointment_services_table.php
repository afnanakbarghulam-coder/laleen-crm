<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointment_services', function (Blueprint $table) {
            $table->decimal('original_price', 10, 2)->nullable()->after('price');
            $table->decimal('discount_amount', 10, 2)->default(0)->after('discount_value');
            $table->string('discount_reason')->nullable()->after('discount_amount');
        });

        // Existing rows predate override tracking - the current price is the
        // best available stand-in for "what it was originally charged at",
        // since there's no discount on record for them either way.
        DB::table('appointment_services')
            ->whereNull('original_price')
            ->update(['original_price' => DB::raw('price')]);
    }

    public function down(): void
    {
        Schema::table('appointment_services', function (Blueprint $table) {
            $table->dropColumn(['original_price', 'discount_amount', 'discount_reason']);
        });
    }
};
