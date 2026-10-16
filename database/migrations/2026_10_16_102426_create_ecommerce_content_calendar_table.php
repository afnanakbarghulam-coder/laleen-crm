<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ecommerce_content_calendar')) {
            return;
        }

        Schema::create('ecommerce_content_calendar', function (Blueprint $table) {
            $table->id();
            $table->string('day_of_week');
            $table->string('content_type')->nullable();
            $table->string('objective')->nullable();
            $table->text('script_concept')->nullable();
            $table->string('person_responsible')->nullable();

            // Deliberately a string, not a DB enum. This codebase already had
            // to drop-and-recreate ecommerce_expenses.category because an enum
            // made adding a value a schema migration; the allowed values are
            // enforced by EcommerceContentCalendar::STATUSES and request
            // validation instead, which is changeable without touching MySQL.
            $table->string('status')->default('Scripted');

            $table->timestamps();

            $table->index('day_of_week');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_content_calendar');
    }
};
