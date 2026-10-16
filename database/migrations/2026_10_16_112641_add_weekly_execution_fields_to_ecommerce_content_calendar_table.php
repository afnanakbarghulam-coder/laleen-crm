<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Brings the ecommerce content calendar up to the salon calendar's execution
 * model: one row per (week, day), split into a Reel module and a Stories
 * module, each independently markable as posted.
 *
 * Existing columns are reused rather than duplicated:
 *   script_concept      -> the Reel script / concept
 *   content_type        -> the Reel content pillar
 *   person_responsible  -> the assignee
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecommerce_content_calendar', function (Blueprint $table) {
            if (!Schema::hasColumn('ecommerce_content_calendar', 'week_start_date')) {
                $table->date('week_start_date')->nullable()->index();
            }
            if (!Schema::hasColumn('ecommerce_content_calendar', 'target_branch')) {
                $table->string('target_branch')->default('All Branches');
            }
            if (!Schema::hasColumn('ecommerce_content_calendar', 'reel_link')) {
                $table->string('reel_link', 2048)->nullable();
            }
            if (!Schema::hasColumn('ecommerce_content_calendar', 'is_reel_posted')) {
                $table->boolean('is_reel_posted')->default(false);
            }
            if (!Schema::hasColumn('ecommerce_content_calendar', 'is_reel_skipped')) {
                $table->boolean('is_reel_skipped')->default(false);
            }
            if (!Schema::hasColumn('ecommerce_content_calendar', 'reel_posted_at')) {
                $table->timestamp('reel_posted_at')->nullable();
            }
            if (!Schema::hasColumn('ecommerce_content_calendar', 'stories_content')) {
                $table->text('stories_content')->nullable();
            }
            if (!Schema::hasColumn('ecommerce_content_calendar', 'are_stories_posted')) {
                $table->boolean('are_stories_posted')->default(false);
            }
            if (!Schema::hasColumn('ecommerce_content_calendar', 'stories_posted_at')) {
                $table->timestamp('stories_posted_at')->nullable();
            }
        });

        // Legacy rows predate the week concept — anchor them to the current
        // week so they stay reachable instead of stranding on a null week.
        $currentWeek = Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString();
        DB::table('ecommerce_content_calendar')->whereNull('week_start_date')->update([
            'week_start_date' => $currentWeek,
        ]);

        // The board is one slot per day per week. Collapse any duplicates the
        // old multi-slot-per-day model allowed before the index is applied,
        // otherwise adding it would fail outright.
        $duplicates = DB::table('ecommerce_content_calendar')
            ->select('week_start_date', 'day_of_week', DB::raw('MIN(id) as keep_id'))
            ->groupBy('week_start_date', 'day_of_week')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('ecommerce_content_calendar')
                ->where('week_start_date', $duplicate->week_start_date)
                ->where('day_of_week', $duplicate->day_of_week)
                ->where('id', '!=', $duplicate->keep_id)
                ->delete();
        }

        Schema::table('ecommerce_content_calendar', function (Blueprint $table) {
            $table->unique(['week_start_date', 'day_of_week'], 'ecc_week_day_unique');
        });
    }

    public function down(): void
    {
        Schema::table('ecommerce_content_calendar', function (Blueprint $table) {
            $table->dropUnique('ecc_week_day_unique');
            $table->dropColumn([
                'week_start_date',
                'target_branch',
                'reel_link',
                'is_reel_posted',
                'is_reel_skipped',
                'reel_posted_at',
                'stories_content',
                'are_stories_posted',
                'stories_posted_at',
            ]);
        });
    }
};
