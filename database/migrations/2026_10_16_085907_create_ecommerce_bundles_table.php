<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ecommerce_bundles')) {
            return;
        }

        Schema::create('ecommerce_bundles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('bundle_price', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_bundles');
    }
};
