<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nova's durable structured business memory (Stage 5) - lives entirely on
 * the isolated 'nova_memory' connection, same as nova_conversations/
 * nova_messages (Stage 4). See app/NovaAI/README.md's NOVA READ-ONLY
 * INTEGRATION RULE. Rows here are populated only from an admin's own
 * explicit statement (App\NovaAI\Services\NovaFactExtractor) - never from
 * CRM data, never from Nova's own inference or recommendations.
 */
return new class extends Migration
{
    protected $connection = 'nova_memory';

    public function up(): void
    {
        Schema::connection('nova_memory')->create('nova_business_facts', function (Blueprint $table) {
            $table->id();
            $table->string('category', 32);
            $table->string('normalized_key', 60);
            $table->string('value', 500);
            $table->enum('status', ['active', 'superseded'])->default('active');
            $table->string('source_message', 2000)->nullable();
            $table->unsignedBigInteger('stated_by_user_id');
            $table->unsignedBigInteger('superseded_by_id')->nullable();
            $table->timestamps();

            $table->index(['normalized_key', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::connection('nova_memory')->dropIfExists('nova_business_facts');
    }
};
