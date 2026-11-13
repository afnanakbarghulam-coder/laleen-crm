<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'contract_pin')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('contract_pin', 255)->nullable()->after('profile_photo');
            });
        }

        if (Schema::hasTable('staff_contracts')) {
            Schema::table('staff_contracts', function (Blueprint $table) {
                $table->unsignedBigInteger('staff_id')->nullable()->change();

                if (!Schema::hasColumn('staff_contracts', 'user_id')) {
                    $table->foreignId('user_id')
                        ->nullable()
                        ->after('staff_id')
                        ->constrained('users')
                        ->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('staff_contracts') && Schema::hasColumn('staff_contracts', 'user_id')) {
            Schema::table('staff_contracts', function (Blueprint $table) {
                $table->dropConstrainedForeignId('user_id');
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'contract_pin')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('contract_pin');
            });
        }
    }
};
