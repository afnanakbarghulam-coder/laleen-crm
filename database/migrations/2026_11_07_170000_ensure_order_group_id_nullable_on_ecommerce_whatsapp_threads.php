<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ecommerce_whatsapp_threads') && Schema::hasColumn('ecommerce_whatsapp_threads', 'order_group_id')) {
            Schema::table('ecommerce_whatsapp_threads', function (Blueprint $table) {
                $table->string('order_group_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // Keep order_group_id nullable on rollback for safety
    }
};
