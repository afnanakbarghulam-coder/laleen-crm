<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_ideas', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('category', 80)->index();
            $table->text('description');
            $table->string('status', 60)->default('New Idea')->index();
            $table->unsignedInteger('upvotes_count')->default(0);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('ops_task_id')->nullable()->constrained('ops_action_items')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('crm_idea_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idea_id')->constrained('crm_ideas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['idea_id', 'user_id']);
        });

        Schema::create('crm_idea_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idea_id')->constrained('crm_ideas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_idea_comments');
        Schema::dropIfExists('crm_idea_votes');
        Schema::dropIfExists('crm_ideas');
    }
};
