<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nova AI's own conversation-memory schema (Stage 4). Lives entirely on the
 * isolated 'nova_memory' connection - see the sibling migration for
 * nova_conversations and app/NovaAI/README.md's NOVA READ-ONLY INTEGRATION
 * RULE. The foreign key below is internal to the nova_memory database only
 * (nova_messages -> nova_conversations, both in the same SQLite file); it
 * never references any CRM table.
 */
return new class extends Migration
{
    protected $connection = 'nova_memory';

    public function up(): void
    {
        Schema::connection('nova_memory')->create('nova_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('conversation_id');
            $table->enum('role', ['user', 'assistant']);
            $table->text('content');
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
            $table->foreign('conversation_id')
                ->references('id')->on('nova_conversations')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::connection('nova_memory')->dropIfExists('nova_messages');
    }
};
