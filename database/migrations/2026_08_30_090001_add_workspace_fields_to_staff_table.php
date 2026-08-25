<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            // Personal
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('email')->nullable()->after('last_name');
            $table->string('phone')->nullable()->after('email');
            $table->date('birthday')->nullable()->after('phone');
            $table->string('address_line1')->nullable()->after('birthday');
            $table->string('city')->nullable()->after('address_line1');
            $table->string('country')->nullable()->after('city');
            $table->string('emergency_contact_name')->nullable()->after('country');
            $table->string('emergency_contact_phone')->nullable()->after('emergency_contact_name');
            $table->string('emergency_contact_relationship')->nullable()->after('emergency_contact_phone');

            // Workspace
            $table->boolean('bookable')->default(true)->after('emergency_contact_relationship');

            // Pay & employment
            $table->date('start_date')->nullable()->after('bookable');
            $table->date('end_date')->nullable()->after('start_date');
            $table->string('employment_type', 30)->nullable()->after('end_date');
            $table->string('staff_member_id')->nullable()->after('employment_type');
            $table->text('internal_notes')->nullable()->after('staff_member_id');
            $table->decimal('hourly_wage', 10, 2)->nullable()->after('internal_notes');
            $table->decimal('commission_rate', 5, 2)->nullable()->after('hourly_wage');

            // Access
            $table->foreignId('user_id')->nullable()->after('commission_rate')
                ->constrained('users')->onDelete('set null');
        });

        // Backfill first/last name from the existing single `name` field so
        // the new Personal > Profile fields aren't blank for current staff.
        DB::table('staff')->select('id', 'name')->get()->each(function ($staff) {
            $parts = preg_split('/\s+/', trim($staff->name), 2);
            DB::table('staff')->where('id', $staff->id)->update([
                'first_name' => $parts[0] ?? $staff->name,
                'last_name' => $parts[1] ?? null,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn([
                'first_name', 'last_name', 'email', 'phone', 'birthday',
                'address_line1', 'city', 'country',
                'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relationship',
                'bookable', 'start_date', 'end_date', 'employment_type', 'staff_member_id',
                'internal_notes', 'hourly_wage', 'commission_rate',
            ]);
        });
    }
};
