<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The specific, distinct services a customer picked out of their
     * combo's pool. One row per chosen service, redeemed independently -
     * the unique constraint is what stops the same service being picked
     * twice within a single package purchase.
     */
    public function up(): void
    {
        Schema::create('client_package_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_package_id')->constrained('client_packages')->onDelete('cascade');
            $table->foreignId('service_id')->nullable()->constrained('services')->onDelete('set null');
            $table->string('service_name');
            $table->string('status', 20)->default('pending'); // pending, redeemed, expired
            $table->foreignId('appointment_service_id')->nullable()->constrained('appointment_services')->onDelete('set null');
            $table->dateTime('redeemed_at')->nullable();
            $table->timestamps();

            $table->unique(['client_package_id', 'service_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_package_services');
    }
};
