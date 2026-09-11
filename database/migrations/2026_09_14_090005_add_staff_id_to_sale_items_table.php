<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records which staff member actually performed each service/upsell
     * line at checkout - previously only the appointment-level staff_id on
     * sales existed, which collapses to one name even when a combo split
     * several services across different team members. Nullable because
     * product and package lines have no single performer to attribute.
     */
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->foreignId('staff_id')->nullable()->after('product_id')->constrained('staff')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('staff_id');
        });
    }
};
