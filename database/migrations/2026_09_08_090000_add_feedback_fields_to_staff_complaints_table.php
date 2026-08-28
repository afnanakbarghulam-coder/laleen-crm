<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expands staff_complaints from a generic staff-discipline log into the
     * Complaints & Feedback record: a customer-reported incident, optionally
     * tied to a deduction against the involved staff member's pay.
     */
    public function up(): void
    {
        Schema::table('staff_complaints', function (Blueprint $table) {
            $table->string('reference_number')->after('id');
            $table->time('complaint_time')->nullable()->after('complaint_date');
            $table->string('branch')->nullable()->after('complaint_time');
            $table->foreignId('customer_id')->nullable()->after('branch')->constrained('customers')->nullOnDelete();
            $table->string('customer_name')->nullable()->after('customer_id');
            $table->string('customer_phone')->nullable()->after('customer_name');
            $table->string('service_received')->nullable()->after('customer_phone');
            $table->enum('deduction_applied', ['Y', 'N'])->default('N')->after('description');
            $table->decimal('deduction_amount', 10, 2)->nullable()->after('deduction_applied');
        });

        Schema::table('staff_complaints', function (Blueprint $table) {
            $table->unique('reference_number');
        });
    }

    public function down(): void
    {
        Schema::table('staff_complaints', function (Blueprint $table) {
            $table->dropUnique(['reference_number']);
            $table->dropConstrainedForeignId('customer_id');
            $table->dropColumn([
                'reference_number', 'complaint_time', 'branch',
                'customer_name', 'customer_phone', 'service_received',
                'deduction_applied', 'deduction_amount',
            ]);
        });
    }
};
