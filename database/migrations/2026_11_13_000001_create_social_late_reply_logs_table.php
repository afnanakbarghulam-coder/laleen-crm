<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('social_late_reply_logs')) {
            Schema::create('social_late_reply_logs', function (Blueprint $table) {
                $table->id();
                $table->dateTime('incident_at')->index();
                $table->string('screenshot_path')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('logged_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('social_late_reply_logs');
    }
};
