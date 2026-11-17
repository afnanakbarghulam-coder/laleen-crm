<?php

use Database\Seeders\ProductRdTrackerSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('product_rd_suppliers')) {
            Schema::create('product_rd_suppliers', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('contact_person')->nullable();
                $table->string('phone_or_whatsapp')->nullable();
                $table->string('country')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('product_rd_items')) {
            Schema::create('product_rd_items', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->date('target_launch_date')->nullable();
                $table->foreignId('winning_supplier_id')
                    ->nullable()
                    ->constrained('product_rd_suppliers')
                    ->nullOnDelete();
                $table->text('notes')->nullable();

                // Packaging & Sticker Columns (Split by Market)
                $table->boolean('qatar_enabled')->default(true);
                $table->string('qatar_bottle_size', 80)->default('500 ml');
                $table->string('qatar_bottle_status', 32)->default('pending');
                $table->string('qatar_sticker_status', 32)->default('blocked_by_bottle');

                $table->boolean('pakistan_enabled')->default(true);
                $table->string('pakistan_bottle_size', 80)->default('500 ml');
                $table->string('pakistan_bottle_status', 32)->default('pending');
                $table->string('pakistan_sticker_status', 32)->default('blocked_by_bottle');

                $table->timestamps();
            });
        } else {
            $hasQaEnabled = Schema::hasColumn('product_rd_items', 'qatar_enabled');
            $hasPkEnabled = Schema::hasColumn('product_rd_items', 'pakistan_enabled');
            if (!$hasQaEnabled || !$hasPkEnabled) {
                Schema::table('product_rd_items', function (Blueprint $table) use ($hasQaEnabled, $hasPkEnabled) {
                    if (!$hasQaEnabled) {
                        $table->boolean('qatar_enabled')->default(true);
                    }
                    if (!$hasPkEnabled) {
                        $table->boolean('pakistan_enabled')->default(true);
                    }
                });
            }
        }

        if (!Schema::hasTable('product_rd_samples')) {
            Schema::create('product_rd_samples', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_rd_item_id')
                    ->constrained('product_rd_items')
                    ->cascadeOnDelete();
                $table->foreignId('product_rd_supplier_id')
                    ->constrained('product_rd_suppliers')
                    ->cascadeOnDelete();

                $table->string('version', 32)->default('V1');
                $table->string('status', 40)->default('waiting_sample');
                $table->string('verdict', 24)->default('pending');
                $table->string('tracking_ref')->nullable();
                $table->date('expected_at')->nullable();
                $table->date('next_followup_at')->nullable();
                $table->decimal('quoted_unit_cost', 10, 2)->nullable();
                $table->string('quoted_currency', 10)->default('QAR');
                $table->string('cost_note', 120)->nullable();

                // 5 Evaluation Scores (1-5)
                $table->unsignedTinyInteger('score_smell')->nullable();
                $table->unsignedTinyInteger('score_cost')->nullable();
                $table->unsignedTinyInteger('score_texture')->nullable();
                $table->unsignedTinyInteger('score_results')->nullable();
                $table->unsignedTinyInteger('score_concept_clear')->nullable();

                $table->text('evaluation_notes')->nullable();
                $table->timestamps();

                $table->unique(
                    ['product_rd_item_id', 'product_rd_supplier_id'],
                    'prd_samples_item_supplier_uniq'
                );
            });
        } else {
            $hasVerdict = Schema::hasColumn('product_rd_samples', 'verdict');
            $hasCostNote = Schema::hasColumn('product_rd_samples', 'cost_note');
            if (!$hasVerdict || !$hasCostNote) {
                Schema::table('product_rd_samples', function (Blueprint $table) use ($hasVerdict, $hasCostNote) {
                    if (!$hasVerdict) {
                        $table->string('verdict', 24)->default('pending')->after('status');
                    }
                    if (!$hasCostNote) {
                        $table->string('cost_note', 120)->nullable()->after('quoted_currency');
                    }
                });
            }
        }

        if (
            Schema::hasTable('product_rd_items')
            && Schema::hasTable('product_rd_suppliers')
            && DB::table('product_rd_items')->count() === 0
            && DB::table('product_rd_suppliers')->count() === 0
        ) {
            (new ProductRdTrackerSeeder())->run();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_rd_samples');
        Schema::dropIfExists('product_rd_items');
        Schema::dropIfExists('product_rd_suppliers');
    }
};
