<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('stock_items')) {
            Schema::create('stock_items', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('item_type', 24)->default('backbar'); // backbar | retail
                $table->foreignId('product_id')->nullable()->unique()->constrained('products')->nullOnDelete();
                $table->string('category', 40)->default('other'); // hair_color, treatments, nails, facials, waxing, consumables, retail, other
                $table->string('unit', 40)->default('Piece');
                $table->decimal('par_level_old_airport', 10, 2)->default(0.00);
                $table->decimal('par_level_al_wakrah', 10, 2)->default(0.00);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['item_type', 'is_active'], 'stock_items_type_active_idx');
                $table->index(['category', 'is_active'], 'stock_items_category_active_idx');
            });
        }

        if (!Schema::hasTable('stock_weeks')) {
            Schema::create('stock_weeks', function (Blueprint $table) {
                $table->id();
                $table->string('branch', 32); // old_airport | al_wakrah
                $table->date('week_start_date'); // Saturday
                $table->date('week_end_date');   // Friday
                $table->string('status', 32)->default('open'); // open | submitted | revision_requested | approved
                $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('submitted_at')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->text('admin_note')->nullable();
                $table->timestamps();

                $table->unique(['branch', 'week_start_date'], 'stock_weeks_branch_start_unique');
                $table->index(['week_start_date', 'status'], 'stock_weeks_start_status_idx');
            });
        }

        if (!Schema::hasTable('stock_week_entries')) {
            Schema::create('stock_week_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('stock_week_id')->constrained('stock_weeks')->cascadeOnDelete();
                $table->foreignId('stock_item_id')->constrained('stock_items')->cascadeOnDelete();
                $table->decimal('opening_qty', 10, 2)->default(0.00);
                $table->decimal('received_qty', 10, 2)->default(0.00);
                $table->decimal('transfer_net_qty', 10, 2)->default(0.00);
                $table->decimal('closing_qty', 10, 2)->nullable();
                $table->decimal('used_qty', 10, 2)->nullable();
                $table->decimal('retail_sold_qty', 10, 2)->default(0.00);
                $table->string('notes')->nullable();
                $table->timestamps();

                $table->unique(['stock_week_id', 'stock_item_id'], 'stock_week_entries_week_item_unique');
            });
        }

        if (!Schema::hasTable('stock_movements')) {
            Schema::create('stock_movements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('stock_week_id')->constrained('stock_weeks')->cascadeOnDelete();
                $table->foreignId('stock_item_id')->constrained('stock_items')->cascadeOnDelete();
                $table->string('branch', 32); // old_airport | al_wakrah
                $table->string('type', 32);   // received | transfer_in | transfer_out | opening_adjustment
                $table->decimal('quantity', 10, 2);
                $table->string('counterparty_branch', 32)->nullable();
                $table->string('reference_note')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['stock_week_id', 'stock_item_id'], 'stock_movements_week_item_idx');
                $table->index(['branch', 'type'], 'stock_movements_branch_type_idx');
            });
        }

        // Automatically sync all existing retail products into stock_items
        if (Schema::hasTable('products') && Schema::hasTable('stock_items')) {
            $now = now();
            $products = DB::table('products')->orderBy('id')->get(['id', 'name', 'low_stock_threshold']);
            foreach ($products as $prod) {
                $exists = DB::table('stock_items')->where('product_id', $prod->id)->exists();
                if (!$exists) {
                    $defaultPar = max(0, (float) ($prod->low_stock_threshold ?? 3));
                    DB::table('stock_items')->insert([
                        'name' => $prod->name,
                        'item_type' => 'retail',
                        'product_id' => $prod->id,
                        'category' => 'retail',
                        'unit' => 'Piece',
                        'par_level_old_airport' => $defaultPar,
                        'par_level_al_wakrah' => $defaultPar,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_week_entries');
        Schema::dropIfExists('stock_weeks');
        Schema::dropIfExists('stock_items');
    }
};
