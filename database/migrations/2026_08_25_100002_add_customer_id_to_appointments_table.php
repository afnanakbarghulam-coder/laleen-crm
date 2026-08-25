<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('phone')
                ->constrained('customers')->onDelete('set null');
        });

        // Link each existing appointment to its customer by phone.
        DB::table('customers')->select('id', 'phone')->orderBy('id')->get()->each(function ($customer) {
            DB::table('appointments')
                ->where('phone', $customer->phone)
                ->update(['customer_id' => $customer->id]);
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
        });
    }
};
