<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_notices', function (Blueprint $table) {
            $table->string('acknowledged_by')->nullable()->after('acknowledged');
            $table->timestamp('acknowledged_at')->nullable()->after('acknowledged_by');
        });
    }

    public function down(): void
    {
        Schema::table('staff_notices', function (Blueprint $table) {
            $table->dropColumn(['acknowledged_by', 'acknowledged_at']);
        });
    }
};
