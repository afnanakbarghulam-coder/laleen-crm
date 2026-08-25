<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * `UserController` has referenced `role` and `profile_photo` on the User
     * model since before this app was handed off, but the `users` table was
     * never actually migrated to have either column — creating or editing a
     * user throws a SQL error. This adds the missing columns and marks the
     * existing account as admin so nobody is locked out of user/staff
     * management once role-based access is enforced.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('staff')->after('email');
            $table->string('profile_photo')->nullable()->after('role');
        });

        DB::table('users')->update(['role' => 'admin']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'profile_photo']);
        });
    }
};
