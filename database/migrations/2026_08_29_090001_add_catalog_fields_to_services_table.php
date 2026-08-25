<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('name')
                ->constrained('service_categories')->onDelete('set null');
            $table->text('description')->nullable()->after('category_id');
            $table->string('treatment_type')->nullable()->after('description');
            $table->string('photo')->nullable()->after('treatment_type');
            $table->string('price_type', 20)->default('fixed')->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn(['description', 'treatment_type', 'photo', 'price_type']);
        });
    }
};
