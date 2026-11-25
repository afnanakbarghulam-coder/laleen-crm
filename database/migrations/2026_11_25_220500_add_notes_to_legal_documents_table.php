<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('legal_documents') && !Schema::hasColumn('legal_documents', 'notes')) {
            Schema::table('legal_documents', function (Blueprint $table) {
                $table->text('notes')->nullable()->after('expiry_date');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('legal_documents') && Schema::hasColumn('legal_documents', 'notes')) {
            Schema::table('legal_documents', function (Blueprint $table) {
                $table->dropColumn('notes');
            });
        }
    }
};
