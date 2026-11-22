<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ad_lead_entries')) {
            Schema::table('ad_lead_entries', function (Blueprint $table) {
                if (! Schema::hasColumn('ad_lead_entries', 'status')) {
                    $table->string('status', 40)->nullable()->after('branch')->index();
                }
                if (! Schema::hasColumn('ad_lead_entries', 'follow_up_date')) {
                    $table->date('follow_up_date')->nullable()->after('status')->index();
                }
                if (! Schema::hasColumn('ad_lead_entries', 'assigned_agent_id')) {
                    $table->foreignId('assigned_agent_id')
                        ->nullable()
                        ->after('follow_up_date')
                        ->constrained('users')
                        ->nullOnDelete();
                }
            });

            // Widen remarks from varchar(255) to text so longer Lead Management remarks sync losslessly.
            if (Schema::hasColumn('ad_lead_entries', 'remarks')) {
                Schema::table('ad_lead_entries', function (Blueprint $table) {
                    $table->text('remarks')->nullable()->change();
                });
            }
        }

        if (Schema::hasTable('leads')) {
            Schema::table('leads', function (Blueprint $table) {
                if (! Schema::hasColumn('leads', 'ad_lead_id')) {
                    $table->unsignedBigInteger('ad_lead_id')->nullable()->after('customer_id')->index();
                }
            });

            if (Schema::hasTable('ad_lead_entries') && Schema::hasColumn('leads', 'ad_lead_id')) {
                // Clean any orphan references defensively before adding the FK.
                DB::table('leads')
                    ->whereNotNull('ad_lead_id')
                    ->whereNotIn('ad_lead_id', DB::table('ad_lead_entries')->select('id'))
                    ->update(['ad_lead_id' => null]);

                $hasForeignKey = collect(Schema::getForeignKeys('leads'))
                    ->contains(fn (array $fk) => in_array('ad_lead_id', $fk['columns'] ?? [], true));

                if (! $hasForeignKey) {
                    Schema::table('leads', function (Blueprint $table) {
                        $table->foreign('ad_lead_id', 'leads_ad_lead_id_foreign')
                            ->references('id')
                            ->on('ad_lead_entries')
                            ->nullOnDelete();
                    });
                }

                // Backfill leads.ad_lead_id from any existing ad_lead_entries.lead_id links (non-destructive).
                if (Schema::hasColumn('ad_lead_entries', 'lead_id')) {
                    $linkedPairs = DB::table('ad_lead_entries')
                        ->whereNotNull('lead_id')
                        ->orderBy('id')
                        ->get(['id', 'lead_id']);

                    foreach ($linkedPairs as $pair) {
                        DB::table('leads')
                            ->where('id', $pair->lead_id)
                            ->whereNull('ad_lead_id')
                            ->update(['ad_lead_id' => $pair->id]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('leads') && Schema::hasColumn('leads', 'ad_lead_id')) {
            $foreignKey = collect(Schema::getForeignKeys('leads'))
                ->first(fn (array $fk) => in_array('ad_lead_id', $fk['columns'] ?? [], true));

            if ($foreignKey) {
                Schema::table('leads', function (Blueprint $table) use ($foreignKey) {
                    if (! empty($foreignKey['name'])) {
                        $table->dropForeign($foreignKey['name']);
                    } else {
                        $table->dropForeign(['ad_lead_id']);
                    }
                });
            }

            Schema::table('leads', function (Blueprint $table) {
                $table->dropIndex(['ad_lead_id']);
                $table->dropColumn('ad_lead_id');
            });
        }

        if (Schema::hasTable('ad_lead_entries')) {
            Schema::table('ad_lead_entries', function (Blueprint $table) {
                if (Schema::hasColumn('ad_lead_entries', 'assigned_agent_id')) {
                    $table->dropConstrainedForeignId('assigned_agent_id');
                }
                if (Schema::hasColumn('ad_lead_entries', 'follow_up_date')) {
                    $table->dropIndex(['follow_up_date']);
                    $table->dropColumn('follow_up_date');
                }
                if (Schema::hasColumn('ad_lead_entries', 'status')) {
                    $table->dropIndex(['status']);
                    $table->dropColumn('status');
                }
            });
        }
    }
};
