<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ops_action_items')) {
            if (! Schema::hasColumn('ops_action_items', 'sla_hours')) {
                Schema::table('ops_action_items', function (Blueprint $table) {
                    $table->integer('sla_hours')->default(48)->after('staff_id');
                });
            }
            if (! Schema::hasColumn('ops_action_items', 'due_at')) {
                Schema::table('ops_action_items', function (Blueprint $table) {
                    $table->timestamp('due_at')->nullable()->after('ball_assigned_at');
                });
            }

            return;
        }

        Schema::create('ops_action_items', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('notes')->nullable();
            $table->text('recommended_solution')->nullable();
            $table->text('proof_note')->nullable();
            $table->string('scope')->default('Laleen Rituals');
            $table->string('impact_lever')->default('revenue_leads');
            $table->string('priority')->default('normal');
            $table->string('pending_with_type')->default('ops_lead');
            $table->string('pending_with_name')->default('Ops Lead');
            $table->json('assigned_user_ids')->nullable();
            $table->json('assigned_names')->nullable();
            $table->unsignedBigInteger('staff_id')->nullable();
            $table->integer('sla_hours')->default(48);
            $table->timestamp('ball_assigned_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->date('week_start_date');
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->string('completed_by')->nullable();
            $table->timestamps();

            $table->index(['is_completed', 'week_start_date'], 'idx_ops_action_completed_week');
            $table->index(['pending_with_type', 'is_completed'], 'idx_ops_action_pending_completed');
            $table->index(['impact_lever', 'is_completed'], 'idx_ops_action_lever_completed');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_action_items');
    }
};
