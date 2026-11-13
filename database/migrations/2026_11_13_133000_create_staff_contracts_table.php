<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('staff') && !Schema::hasColumn('staff', 'contract_pin')) {
            Schema::table('staff', function (Blueprint $table) {
                $table->string('contract_pin', 255)->nullable()->after('user_id');
            });
        }

        if (!Schema::hasTable('staff_contracts')) {
            Schema::create('staff_contracts', function (Blueprint $table) {
                $table->id();
                $table->string('contract_number', 40)->unique();
                $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
                $table->string('template_key', 80)->index();
                $table->string('role_title', 120);
                $table->string('branch', 40)->default('old_airport');
                $table->string('currency', 8)->default('QAR');
                $table->decimal('base_salary', 12, 2)->default(0);
                $table->decimal('housing_allowance', 12, 2)->default(0);
                $table->decimal('transport_allowance', 12, 2)->default(0);
                $table->decimal('other_allowance', 12, 2)->default(0);
                $table->date('start_date')->nullable();
                $table->date('expiry_date')->nullable();
                $table->longText('clauses_body');
                $table->string('status', 30)->default('pending_signature')->index();
                $table->string('secure_token', 80)->unique();
                $table->string('signed_name', 160)->nullable();
                $table->longText('signature_data')->nullable();
                $table->string('signature_type', 20)->nullable();
                $table->timestamp('signed_at')->nullable();
                $table->string('signed_ip', 45)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_contracts');

        if (Schema::hasTable('staff') && Schema::hasColumn('staff', 'contract_pin')) {
            Schema::table('staff', function (Blueprint $table) {
                $table->dropColumn('contract_pin');
            });
        }
    }
};
