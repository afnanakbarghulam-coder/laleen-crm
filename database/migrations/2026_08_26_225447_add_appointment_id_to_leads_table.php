<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // Links a lead auto-generated from a specific No Show/Cancelled
            // appointment, so re-toggling that one appointment's status
            // updates its own dedicated lead instead of ever touching an
            // unrelated, independently-active lead for the same client.
            $table->foreignId('appointment_id')->nullable()->after('customer_id')
                ->constrained('appointments')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('appointment_id');
        });
    }
};
