<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('daily_targets', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->integer('daily_target')->default(0);
            $table->integer('actual_bookings')->default(0);
            $table->decimal('percentage_achieved', 5, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('daily_targets');
    }
};
