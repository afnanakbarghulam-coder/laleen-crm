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
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('phone');
            $table->unsignedBigInteger('assigned_agent_id')->nullable();
            $table->string('lead_source')->nullable();
            $table->date('followup_date')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'done'])->default('pending');
            $table->timestamps();

            $table->foreign('assigned_agent_id')->references('id')->on('users')->onDelete('set null');
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
