<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Dedicated section on a notice for what the staff member must do to correct the issue. */
    public function up(): void
    {
        Schema::table('staff_notices', function (Blueprint $table) {
            $table->text('corrective_actions')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('staff_notices', function (Blueprint $table) {
            $table->dropColumn('corrective_actions');
        });
    }
};
