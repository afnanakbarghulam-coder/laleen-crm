<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('social_content_weeks')) {
            Schema::create('social_content_weeks', function (Blueprint $table) {
                $table->id();
                $table->date('week_start_date')->unique();
                $table->text('strategy_objective')->nullable();
                $table->json('pro_tips')->nullable();
                $table->text('hashtags')->nullable();
                $table->string('source_pdf_name')->nullable();
                $table->timestamp('uploaded_at')->nullable();
                $table->unsignedBigInteger('uploaded_by')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('social_content_weeks', function (Blueprint $table) {
                if (!Schema::hasColumn('social_content_weeks', 'strategy_objective')) {
                    $table->text('strategy_objective')->nullable();
                }
                if (!Schema::hasColumn('social_content_weeks', 'pro_tips')) {
                    $table->json('pro_tips')->nullable();
                }
                if (!Schema::hasColumn('social_content_weeks', 'hashtags')) {
                    $table->text('hashtags')->nullable();
                }
                if (!Schema::hasColumn('social_content_weeks', 'source_pdf_name')) {
                    $table->string('source_pdf_name')->nullable();
                }
                if (!Schema::hasColumn('social_content_weeks', 'uploaded_at')) {
                    $table->timestamp('uploaded_at')->nullable();
                }
                if (!Schema::hasColumn('social_content_weeks', 'uploaded_by')) {
                    $table->unsignedBigInteger('uploaded_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('social_content_plans')) {
            Schema::table('social_content_plans', function (Blueprint $table) {
                if (!Schema::hasColumn('social_content_plans', 'date')) {
                    $table->date('date')->nullable()->after('week_start_date');
                }
                if (!Schema::hasColumn('social_content_plans', 'branch')) {
                    $table->string('branch', 80)->default('All Branches')->after('target_branch');
                }
                if (!Schema::hasColumn('social_content_plans', 'assigned_user_id')) {
                    $table->unsignedBigInteger('assigned_user_id')->nullable()->after('assigned_to');
                }
                if (!Schema::hasColumn('social_content_plans', 'focus')) {
                    $table->string('focus', 255)->nullable()->after('assigned_user_id');
                }
                if (!Schema::hasColumn('social_content_plans', 'format')) {
                    $table->string('format', 120)->nullable()->after('focus');
                }
                if (!Schema::hasColumn('social_content_plans', 'visual_concept')) {
                    $table->text('visual_concept')->nullable()->after('format');
                }
                if (!Schema::hasColumn('social_content_plans', 'caption_cta')) {
                    $table->text('caption_cta')->nullable()->after('visual_concept');
                }
                if (!Schema::hasColumn('social_content_plans', 'proof_url')) {
                    $table->string('proof_url', 2048)->nullable()->after('caption_cta');
                }
                if (!Schema::hasColumn('social_content_plans', 'is_story_verified')) {
                    $table->boolean('is_story_verified')->default(false)->after('proof_url');
                }
                if (!Schema::hasColumn('social_content_plans', 'status')) {
                    $table->string('status', 24)->default('pending')->after('is_story_verified');
                }
                if (!Schema::hasColumn('social_content_plans', 'posted_at')) {
                    $table->timestamp('posted_at')->nullable()->after('status');
                }
            });

            // Non-destructive backfill of existing rows so legacy content & posted proofs carry over seamlessly
            $dayOffsets = [
                'monday' => 0,
                'tuesday' => 1,
                'wednesday' => 2,
                'thursday' => 3,
                'friday' => 4,
                'saturday' => 5,
                'sunday' => 6,
            ];

            DB::table('social_content_plans')
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($dayOffsets) {
                    foreach ($rows as $row) {
                        $updates = [];

                        if (empty($row->date) && !empty($row->week_start_date)) {
                            try {
                                $base = substr(trim((string) $row->week_start_date), 0, 10);
                                $offset = $dayOffsets[strtolower(trim((string) $row->day_of_week))] ?? 0;
                                $updates['date'] = Carbon::createFromFormat('Y-m-d', $base)->addDays($offset)->toDateString();
                            } catch (\Throwable) {
                                // Ignore malformed legacy date if any
                            }
                        }

                        $legacyBranch = trim((string) ($row->target_branch ?? ''));
                        if ($legacyBranch === 'Wakrah') {
                            $legacyBranch = 'Al Wakrah';
                        }
                        if (empty($row->branch) || ($row->branch === 'All Branches' && $legacyBranch !== '' && $legacyBranch !== 'All Branches')) {
                            $updates['branch'] = $legacyBranch ?: 'All Branches';
                        }

                        if (empty($row->visual_concept)) {
                            if (!empty($row->reel_content)) {
                                $updates['visual_concept'] = $row->reel_content;
                                if (empty($row->format)) {
                                    $updates['format'] = 'Reel / Short Video';
                                }
                                if (empty($row->caption_cta) && !empty($row->stories_content)) {
                                    $updates['caption_cta'] = $row->stories_content;
                                }
                            } elseif (!empty($row->stories_content)) {
                                $updates['visual_concept'] = $row->stories_content;
                                if (empty($row->format)) {
                                    $updates['format'] = 'Story Series (3 Frames)';
                                }
                            }
                        }

                        if (empty($row->proof_url) && !empty($row->reel_link)) {
                            $updates['proof_url'] = $row->reel_link;
                        }

                        if (($row->status ?? 'pending') === 'pending') {
                            if (!empty($row->is_reel_posted)) {
                                $updates['status'] = 'posted';
                                $updates['posted_at'] = $row->reel_posted_at ?: $row->updated_at;
                            } elseif (!empty($row->are_stories_posted) && empty($row->reel_content)) {
                                $updates['is_story_verified'] = true;
                                $updates['status'] = 'posted';
                                $updates['posted_at'] = $row->stories_posted_at ?: $row->updated_at;
                            }
                        }

                        if (!empty($updates)) {
                            DB::table('social_content_plans')->where('id', $row->id)->update($updates);
                        }
                    }
                });
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive in production environments
    }
};
