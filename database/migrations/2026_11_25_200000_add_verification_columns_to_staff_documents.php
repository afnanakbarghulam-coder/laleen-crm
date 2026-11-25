<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('staff_documents')) {
            return;
        }

        Schema::table('staff_documents', function (Blueprint $table) {
            if (!Schema::hasColumn('staff_documents', 'verification_status')) {
                $table->string('verification_status', 32)->default('verified')->after('uploaded_by');
            }
            if (!Schema::hasColumn('staff_documents', 'verified_by')) {
                $table->foreignId('verified_by')->nullable()->after('verification_status')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('staff_documents', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('verified_by');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('staff_documents')) {
            return;
        }

        Schema::table('staff_documents', function (Blueprint $table) {
            if (Schema::hasColumn('staff_documents', 'verified_by')) {
                $table->dropConstrainedForeignId('verified_by');
            }
            $dropCols = array_values(array_filter([
                'verification_status',
                'verified_at',
            ], fn (string $col) => Schema::hasColumn('staff_documents', $col)));

            if (!empty($dropCols)) {
                $table->dropColumn($dropCols);
            }
        });
    }
};
