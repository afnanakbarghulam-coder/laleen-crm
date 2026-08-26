<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->renameColumn('correction_done', 'needful_done');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('booking_status');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('booking_status')->nullable();
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->renameColumn('needful_done', 'correction_done');
        });
    }
};
