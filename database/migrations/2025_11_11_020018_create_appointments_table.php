<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('customer_name');
            $table->string('phone');
            $table->dateTime('appointment_datetime');
            $table->string('service_name');
            $table->enum('branch', ['old_airport', 'wakrah', 'home_service']);
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('lifetime_revenue', 10, 2)->default(0);
            $table->unsignedBigInteger('booking_agent_id')->nullable();
            $table->unsignedBigInteger('staff_id')->nullable();
            $table->timestamps();

            $table->foreign('booking_agent_id')->references('id')->on('users')->onDelete('set null');
            
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
