<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('services') && !Schema::hasColumn('services', 'steps')) {
            Schema::table('services', function (Blueprint $table) {
                $table->json('steps')->nullable()->after('description');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('services') && Schema::hasColumn('services', 'steps')) {
            Schema::table('services', function (Blueprint $table) {
                $table->dropColumn('steps');
            });
        }
    }
};
