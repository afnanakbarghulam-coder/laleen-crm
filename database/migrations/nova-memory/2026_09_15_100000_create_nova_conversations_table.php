<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nova AI's own conversation-memory schema (Stage 4). Lives entirely on the
 * isolated 'nova_memory' connection (see config/database.php) - a completely
 * separate SQLite file from the CRM's own database. This migration never
 * touches, references, or shares a table with any CRM schema. See
 * app/NovaAI/README.md's NOVA READ-ONLY INTEGRATION RULE.
 */
return new class extends Migration
{
    protected $connection = 'nova_memory';

    public function up(): void
    {
        Schema::connection('nova_memory')->create('nova_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('owner_user_id');
            $table->timestamp('started_at');
            $table->timestamp('last_active_at');
            $table->timestamps();

            $table->index('owner_user_id');
        });
    }

    public function down(): void
    {
        Schema::connection('nova_memory')->dropIfExists('nova_conversations');
    }
};
