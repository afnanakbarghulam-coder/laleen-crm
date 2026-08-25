<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('branch', ['old_airport', 'wakrah', 'both'])->default('both');
            $table->json('skills')->nullable();
            $table->json('working_hours')->nullable();
            $table->json('weekly_off')->nullable(); 
            $table->enum('availability_status', ['present', 'on-leave', 'sick'])->default('present');
            $table->date('off_from')->nullable();
            $table->date('off_to')->nullable();
            $table->string('profile_picture')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('staff');
    }
};
