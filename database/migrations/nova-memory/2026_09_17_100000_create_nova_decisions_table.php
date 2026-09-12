<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nova's decision memory (Stage 6) - lives entirely on the isolated
 * 'nova_memory' connection, same as every other Nova memory table. See
 * app/NovaAI/README.md's NOVA READ-ONLY INTEGRATION RULE. A row here
 * records that the owner explicitly approved/committed to something -
 * never a Nova recommendation alone, and never anything that causes any
 * CRM record to change. review_date is stored for Nova to mention when
 * relevant only - there is no scheduler, job, or notification tied to it.
 */
return new class extends Migration
{
    protected $connection = 'nova_memory';

    public function up(): void
    {
        Schema::connection('nova_memory')->create('nova_decisions', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->string('description', 1000)->nullable();
            $table->string('category', 32);
            $table->enum('status', ['active', 'completed', 'cancelled', 'reversed'])->default('active');
            $table->date('review_date')->nullable();
            $table->string('result_summary', 500)->nullable();
            $table->unsignedBigInteger('decided_by_user_id');
            $table->string('source_message', 2000)->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::connection('nova_memory')->dropIfExists('nova_decisions');
    }
};
