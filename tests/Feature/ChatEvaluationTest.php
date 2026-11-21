<?php

namespace Tests\Feature;

use App\Models\KpiChatEvaluation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatEvaluationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Request-payload shape: all 15 questions, given numbers forced to
     * 'fail' (rest 'pass'), with an optional remark per question number.
     *
     * @return array<string, array{answer: string, remark: ?string}>
     */
    private function submittedAnswers(array $failNumbers = [], array $remarks = []): array
    {
        $answers = [];

        foreach (array_keys(KpiChatEvaluation::QUESTIONS) as $num) {
            $answers[KpiChatEvaluation::answerKey($num)] = [
                'answer' => in_array($num, $failNumbers, true) ? 'fail' : 'pass',
                'remark' => $remarks[$num] ?? null,
            ];
        }

        return $answers;
    }

    /**
     * Stored-column shape: all 15 questions, given numbers scored 0 (rest
     * full weight), with an optional remark per question number — for
     * seeding a KpiChatEvaluation::create() fixture directly.
     *
     * @return array<string, array{score: int, remark: ?string}>
     */
    private function storedAnswers(array $failNumbers = [], array $remarks = []): array
    {
        $answers = [];

        foreach (KpiChatEvaluation::QUESTIONS as $num => $question) {
            $answers[KpiChatEvaluation::answerKey($num)] = [
                'score' => in_array($num, $failNumbers, true) ? 0 : $question['weight'],
                'remark' => $remarks[$num] ?? null,
            ];
        }

        return $answers;
    }

    public function test_max_score_is_derived_from_question_weights_and_equals_170(): void
    {
        $this->assertSame(170, KpiChatEvaluation::maxScore());
    }

    public function test_breakdown_groups_cover_every_question_exactly_once_and_sum_to_max_score(): void
    {
        $allNumbers = collect(KpiChatEvaluation::BREAKDOWN_GROUPS)->flatten()->sort()->values();

        $this->assertSame(array_keys(KpiChatEvaluation::QUESTIONS), $allNumbers->all());

        $maxSum = collect(KpiChatEvaluation::BREAKDOWN_GROUPS)
            ->flatten()
            ->sum(fn ($num) => KpiChatEvaluation::QUESTIONS[$num]['weight']);

        $this->assertSame(KpiChatEvaluation::maxScore(), $maxSum);
    }

    public function test_tier_for_follows_the_0_between_100_rule(): void
    {
        $this->assertSame('red', KpiChatEvaluation::tierFor(0));
        $this->assertSame('amber', KpiChatEvaluation::tierFor(1));
        $this->assertSame('amber', KpiChatEvaluation::tierFor(99.9));
        $this->assertSame('green', KpiChatEvaluation::tierFor(100));
    }

    public function test_index_and_create_pages_render_with_category_grouping(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->get(route('kpi.chat-eval.index'))
            ->assertOk()
            ->assertSee('out of 170 points', false);

        $response = $this->actingAs($user)->get(route('kpi.chat-eval.create'));
        $response->assertOk();
        $response->assertSee('Add a remark (optional)', false);

        foreach (KpiChatEvaluation::QUESTIONS as $question) {
            $response->assertSee($question['text'], false);
        }

        foreach (array_keys(KpiChatEvaluation::BREAKDOWN_GROUPS) as $category) {
            $response->assertSee($category, false);
        }
    }

    public function test_store_computes_weighted_total_score_and_saves_score_and_remark_per_question(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $agent = User::factory()->create(['role' => 'agent']);

        // Fails: Q4 (15), Q9 (10), Q13 (5), Q15 (5) -> lost 35 -> 170-35=135 -> 79.41%
        $payload = [
            'agent_id' => $agent->id,
            'evaluation_date' => '2026-10-05',
            'answers' => $this->submittedAnswers([4, 9, 13, 15], [4 => 'Did not follow up after client went quiet.']),
        ];

        $response = $this->actingAs($user)->post(route('kpi.chat-eval.store'), $payload);

        $this->assertDatabaseCount('kpi_chat_evaluations', 1);

        $report = KpiChatEvaluation::first();

        $response->assertRedirect(route('kpi.chat-eval.show', $report));
        $this->assertSame($agent->id, $report->agent_id);
        $this->assertSame($user->id, $report->evaluator_id);
        $this->assertSame(135, $report->total_score);
        $this->assertEquals(79.41, (float) $report->percentage);
        $this->assertSame('Pass', $report->grade());

        $this->assertSame(['score' => 0, 'remark' => 'Did not follow up after client went quiet.'], $report->answers['q4']);
        $this->assertSame(['score' => 15, 'remark' => null], $report->answers['q1']);
    }

    public function test_store_ignores_any_client_submitted_score_and_always_computes_it_server_side(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $agent = User::factory()->create(['role' => 'agent']);

        $payload = [
            'agent_id' => $agent->id,
            'evaluation_date' => '2026-10-05',
            'answers' => $this->submittedAnswers(),
            'total_score' => 99999,
            'percentage' => 9999,
        ];

        // Also try to forge a raw score straight into the answers payload —
        // the validator doesn't even accept an "answers.q1.score" field, so
        // this must be silently dropped rather than trusted.
        $payload['answers']['q1']['score'] = 99999;

        $this->actingAs($user)->post(route('kpi.chat-eval.store'), $payload);

        $report = KpiChatEvaluation::first();

        $this->assertSame(170, $report->total_score);
        $this->assertEquals(100.0, (float) $report->percentage);
        $this->assertSame(15, $report->answers['q1']['score']);
    }

    public function test_store_rejects_non_binary_answers_and_invalid_agent(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $notAnAgent = User::factory()->create(['role' => 'user']);

        $answers = $this->submittedAnswers();
        $answers['q1']['answer'] = 'yes';

        $payload = [
            'agent_id' => $notAnAgent->id,
            'evaluation_date' => '2026-10-05',
            'answers' => $answers,
        ];

        $this->actingAs($user)
            ->post(route('kpi.chat-eval.store'), $payload)
            ->assertSessionHasErrors(['agent_id', 'answers.q1.answer']);

        $this->assertDatabaseCount('kpi_chat_evaluations', 0);
    }

    public function test_show_page_renders_categories_scores_and_conditional_remarks(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $agent = User::factory()->create(['role' => 'agent', 'name' => 'Test Agent']);

        $answers = $this->storedAnswers([4], [4 => 'Dropped the follow-up thread.']);
        $report = KpiChatEvaluation::create([
            'agent_id' => $agent->id,
            'evaluator_id' => $user->id,
            'evaluation_date' => '2026-10-05',
            'answers' => $answers,
            'total_score' => 155,
            'percentage' => round(155 / 170 * 100, 2),
        ]);

        $response = $this->actingAs($user)->get(route('kpi.chat-eval.show', $report));

        $response->assertOk();
        $response->assertSee('Test Agent');
        $response->assertSee('Followed up if client went quiet?');
        $response->assertSee('Follow-Up');
        $response->assertSee('0/15', false);
        $response->assertSee('Dropped the follow-up thread.');
        $response->assertSee('Edit Report');

        // Only Q4 has a remark — its block is the only one that should render.
        $html = $response->getContent();
        $this->assertSame(1, substr_count($html, 'class="ce-remark"'));
    }

    public function test_edit_page_prefills_agent_date_remark_and_checks_saved_answers(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $agent = User::factory()->create(['role' => 'agent', 'name' => 'Edit Agent']);

        $answers = $this->storedAnswers([2], [2 => 'Never asked for the booking directly.']);
        $report = KpiChatEvaluation::create([
            'agent_id' => $agent->id,
            'evaluator_id' => $user->id,
            'evaluation_date' => '2026-10-05',
            'answers' => $answers,
            'total_score' => 155,
            'percentage' => round(155 / 170 * 100, 2),
        ]);

        $response = $this->actingAs($user)->get(route('kpi.chat-eval.edit', $report));

        $response->assertOk();
        $response->assertSee('Edit Agent');
        $response->assertSee('Never asked for the booking directly.');

        $html = $response->getContent();

        $this->assertMatchesRegularExpression('/name="answers\[q1\]\[answer\]" value="pass"[^>]*checked/', $html);
        $this->assertMatchesRegularExpression('/name="answers\[q2\]\[answer\]" value="fail"[^>]*checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/name="answers\[q2\]\[answer\]" value="pass"[^>]*checked/', $html);
    }

    public function test_update_recalculates_score_updates_remarks_and_preserves_original_evaluator(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $otherUser = User::factory()->create(['role' => 'admin']);
        $agent = User::factory()->create(['role' => 'agent']);

        $report = KpiChatEvaluation::create([
            'agent_id' => $agent->id,
            'evaluator_id' => $user->id,
            'evaluation_date' => '2026-10-05',
            'answers' => $this->storedAnswers([4, 9, 13, 15]),
            'total_score' => 135,
            'percentage' => 79.41,
        ]);

        $payload = [
            'agent_id' => $agent->id,
            'evaluation_date' => '2026-10-06',
            'answers' => $this->submittedAnswers([], [1 => 'Closed cleanly on the first attempt.']),
        ];

        $this->actingAs($otherUser)
            ->put(route('kpi.chat-eval.update', $report), $payload)
            ->assertRedirect(route('kpi.chat-eval.show', $report));

        $report->refresh();

        $this->assertSame(170, $report->total_score);
        $this->assertEquals(100.0, (float) $report->percentage);
        $this->assertSame('2026-10-06', $report->evaluation_date->toDateString());
        $this->assertSame('Closed cleanly on the first attempt.', $report->answers['q1']['remark']);
        // update() must not reassign evaluator_id to whoever is editing.
        $this->assertSame($user->id, $report->evaluator_id);
    }

    public function test_destroy_deletes_the_report(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $agent = User::factory()->create(['role' => 'agent']);

        $report = KpiChatEvaluation::create([
            'agent_id' => $agent->id,
            'evaluator_id' => $user->id,
            'evaluation_date' => '2026-10-05',
            'answers' => $this->storedAnswers(),
            'total_score' => 170,
            'percentage' => 100,
        ]);

        $this->actingAs($user)
            ->delete(route('kpi.chat-eval.destroy', $report))
            ->assertRedirect(route('kpi.chat-eval.index'));

        $this->assertDatabaseCount('kpi_chat_evaluations', 0);
    }

    public function test_store_saves_overall_notes(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $agent = User::factory()->create(['role' => 'agent']);

        $payload = [
            'agent_id' => $agent->id,
            'evaluation_date' => '2026-10-05',
            'answers' => $this->submittedAnswers(),
            'notes' => 'Strong overall call quality this cycle.',
        ];

        $this->actingAs($user)->post(route('kpi.chat-eval.store'), $payload);

        $report = KpiChatEvaluation::first();

        $this->assertSame('Strong overall call quality this cycle.', $report->notes);
    }

    public function test_store_allows_omitted_notes(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $agent = User::factory()->create(['role' => 'agent']);

        $payload = [
            'agent_id' => $agent->id,
            'evaluation_date' => '2026-10-05',
            'answers' => $this->submittedAnswers(),
        ];

        $this->actingAs($user)
            ->post(route('kpi.chat-eval.store'), $payload)
            ->assertSessionHasNoErrors();

        $this->assertNull(KpiChatEvaluation::first()->notes);
    }

    public function test_update_overwrites_overall_notes(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $agent = User::factory()->create(['role' => 'agent']);

        $report = KpiChatEvaluation::create([
            'agent_id' => $agent->id,
            'evaluator_id' => $user->id,
            'evaluation_date' => '2026-10-05',
            'answers' => $this->storedAnswers(),
            'total_score' => 170,
            'percentage' => 100,
            'notes' => 'Original note.',
        ]);

        $payload = [
            'agent_id' => $agent->id,
            'evaluation_date' => '2026-10-05',
            'answers' => $this->submittedAnswers(),
            'notes' => 'Revised note after re-review.',
        ];

        $this->actingAs($user)->put(route('kpi.chat-eval.update', $report), $payload);

        $this->assertSame('Revised note after re-review.', $report->refresh()->notes);
    }

    public function test_show_page_renders_leader_notes_only_when_present(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $agent = User::factory()->create(['role' => 'agent']);

        $withNotes = KpiChatEvaluation::create([
            'agent_id' => $agent->id,
            'evaluator_id' => $user->id,
            'evaluation_date' => '2026-10-05',
            'answers' => $this->storedAnswers(),
            'total_score' => 170,
            'percentage' => 100,
            'notes' => 'Coach on follow-up discipline.',
        ]);

        $this->actingAs($user)
            ->get(route('kpi.chat-eval.show', $withNotes))
            ->assertOk()
            ->assertSee('Leader Notes')
            ->assertSee('Coach on follow-up discipline.');

        $withoutNotes = KpiChatEvaluation::create([
            'agent_id' => $agent->id,
            'evaluator_id' => $user->id,
            'evaluation_date' => '2026-10-06',
            'answers' => $this->storedAnswers(),
            'total_score' => 170,
            'percentage' => 100,
            'notes' => null,
        ]);

        $this->actingAs($user)
            ->get(route('kpi.chat-eval.show', $withoutNotes))
            ->assertOk()
            ->assertDontSee('Leader Notes');
    }

    public function test_edit_page_prefills_overall_notes_textarea(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $agent = User::factory()->create(['role' => 'agent']);

        $report = KpiChatEvaluation::create([
            'agent_id' => $agent->id,
            'evaluator_id' => $user->id,
            'evaluation_date' => '2026-10-05',
            'answers' => $this->storedAnswers(),
            'total_score' => 170,
            'percentage' => 100,
            'notes' => 'Pre-filled note for editing.',
        ]);

        $this->actingAs($user)
            ->get(route('kpi.chat-eval.edit', $report))
            ->assertOk()
            ->assertSee('Pre-filled note for editing.');
    }

    public function test_create_page_hides_every_remark_field_by_default(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $html = $this->actingAs($user)->get(route('kpi.chat-eval.create'))->getContent();

        $this->assertSame(count(KpiChatEvaluation::QUESTIONS), substr_count($html, 'data-remark-open="false"'));
        $this->assertSame(0, substr_count($html, 'data-remark-open="true"'));
        $this->assertSame(count(KpiChatEvaluation::QUESTIONS), substr_count($html, 'display:none;'));
    }

    public function test_edit_page_opens_only_the_remark_field_that_has_a_saved_remark(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $agent = User::factory()->create(['role' => 'agent']);

        $report = KpiChatEvaluation::create([
            'agent_id' => $agent->id,
            'evaluator_id' => $user->id,
            'evaluation_date' => '2026-10-05',
            'answers' => $this->storedAnswers([4], [4 => 'Dropped the follow-up thread.']),
            'total_score' => 155,
            'percentage' => round(155 / 170 * 100, 2),
        ]);

        $html = $this->actingAs($user)->get(route('kpi.chat-eval.edit', $report))->getContent();

        $this->assertSame(1, substr_count($html, 'data-remark-open="true"'));
        $this->assertSame(count(KpiChatEvaluation::QUESTIONS) - 1, substr_count($html, 'data-remark-open="false"'));
    }

    public function test_selectable_agents_include_areeba_zoya_and_laden_regardless_of_laden_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Afnan']);
        $areeba = User::factory()->create(['role' => 'agent', 'name' => 'Areeba']);
        $zoya = User::factory()->create(['role' => 'agent', 'name' => 'Zoya']);
        $laden = User::factory()->create(['role' => 'manager', 'name' => 'Laden']);
        $otherManager = User::factory()->create(['role' => 'manager', 'name' => 'Ahana']);
        $regularUser = User::factory()->create(['role' => 'user', 'name' => 'Client User']);

        $selectable = KpiChatEvaluation::selectableAgents();

        $this->assertSame(
            [$areeba->id, $zoya->id, $laden->id],
            $selectable->pluck('id')->all()
        );
        $this->assertNotContains($otherManager->id, $selectable->pluck('id')->all());
        $this->assertNotContains($regularUser->id, $selectable->pluck('id')->all());

        $response = $this->actingAs($admin)->get(route('kpi.chat-eval.create'));
        $response->assertOk();
        $response->assertSee('All Agents');
        $response->assertSee('Areeba');
        $response->assertSee('Zoya');
        $response->assertSee('Laden');
        $response->assertDontSee('Ahana');
    }

    public function test_store_and_edit_support_single_pair_and_all_agents_multi_selection(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Afnan']);
        $areeba = User::factory()->create(['role' => 'agent', 'name' => 'Areeba']);
        $zoya = User::factory()->create(['role' => 'agent', 'name' => 'Zoya']);
        $laden = User::factory()->create(['role' => 'manager', 'name' => 'Laden']);

        // (a) Single agent: Zoya
        $this->actingAs($admin)->post(route('kpi.chat-eval.store'), [
            'agent_ids' => [$zoya->id],
            'evaluation_date' => '2026-11-10',
            'answers' => $this->submittedAnswers([14]),
        ])->assertRedirect();

        $singleReport = KpiChatEvaluation::latest('id')->first();
        $this->assertFalse($singleReport->isAllAgents());
        $this->assertSame([$zoya->id], $singleReport->evaluatedAgentIds());
        $this->assertSame('Zoya', $singleReport->formattedAgentsLabel());
        $this->assertSame('chat-evaluation-2026-11-10-zoya.pdf', $singleReport->pdfFilename());

        // (b) Two agents: Laden + Zoya
        $this->actingAs($admin)->post(route('kpi.chat-eval.store'), [
            'agent_ids' => [$zoya->id, $laden->id],
            'evaluation_date' => '2026-11-11',
            'answers' => $this->submittedAnswers([4], [4 => 'Delayed follow-up']),
        ])->assertRedirect();

        $pairReport = KpiChatEvaluation::latest('id')->first();
        $this->assertFalse($pairReport->isAllAgents());
        $this->assertEqualsCanonicalizing([$zoya->id, $laden->id], $pairReport->evaluatedAgentIds());
        $this->assertSame('Laden + Zoya', $pairReport->formattedAgentsLabel());
        $this->assertSame('chat-evaluation-2026-11-11-laden-zoya.pdf', $pairReport->pdfFilename());

        // Verify Edit page pre-checks Laden & Zoya, and leaves Areeba unchecked
        $editHtml = $this->actingAs($admin)->get(route('kpi.chat-eval.edit', $pairReport))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/value="' . $zoya->id . '"[^>]*checked/s', $editHtml);
        $this->assertMatchesRegularExpression('/value="' . $laden->id . '"[^>]*checked/s', $editHtml);
        $this->assertDoesNotMatchRegularExpression('/value="' . $areeba->id . '"[^>]*checked/s', $editHtml);

        // (c) Update pair report -> All Agents (Areeba, Zoya, Laden)
        $this->actingAs($admin)->put(route('kpi.chat-eval.update', $pairReport), [
            'agent_ids' => [$areeba->id, $zoya->id, $laden->id],
            'evaluation_date' => '2026-11-11',
            'answers' => $this->submittedAnswers(),
            'notes' => 'Full team QA audit.',
        ])->assertRedirect(route('kpi.chat-eval.show', $pairReport));

        $pairReport->refresh()->load('agents');
        $this->assertTrue($pairReport->isAllAgents());
        $this->assertEqualsCanonicalizing([$areeba->id, $zoya->id, $laden->id], $pairReport->evaluatedAgentIds());
        $this->assertSame('All Agents (Areeba, Zoya, Laden)', $pairReport->formattedAgentsLabel());
        $this->assertSame('chat-evaluation-2026-11-11-all-agents.pdf', $pairReport->pdfFilename());

        // Verify Edit page pre-checks all three and activates All Agents checkbox
        $editAllHtml = $this->actingAs($admin)->get(route('kpi.chat-eval.edit', $pairReport))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/data-all-agents-checkbox[^>]*checked/', $editAllHtml);

        // Verify index & show render formatted labels and MTD/agent filtering cleanly
        $this->actingAs($admin)
            ->get(route('kpi.chat-eval.show', $pairReport))
            ->assertOk()
            ->assertSee('All Agents (Areeba, Zoya, Laden)')
            ->assertSee('Download PDF');

        $this->actingAs($admin)
            ->get(route('kpi.chat-eval.index', ['month' => '2026-11', 'agent_id' => $laden->id]))
            ->assertOk()
            ->assertSee('All Agents (Areeba, Zoya, Laden)')
            ->assertDontSee('chat-evaluation-2026-11-10-zoya.pdf')
            ->assertSee('1 graded');
    }

    public function test_store_requires_at_least_one_valid_agent_in_agent_ids(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $areeba = User::factory()->create(['role' => 'agent', 'name' => 'Areeba']);
        $ineligible = User::factory()->create(['role' => 'user', 'name' => 'Random User']);

        // Missing / empty agent_ids
        $this->actingAs($admin)
            ->post(route('kpi.chat-eval.store'), [
                'agent_ids' => [],
                'evaluation_date' => '2026-11-12',
                'answers' => $this->submittedAnswers(),
            ])
            ->assertSessionHasErrors(['agent_ids']);

        // Contains an ineligible user ID alongside valid agent
        $this->actingAs($admin)
            ->post(route('kpi.chat-eval.store'), [
                'agent_ids' => [$areeba->id, $ineligible->id],
                'evaluation_date' => '2026-11-12',
                'answers' => $this->submittedAnswers(),
            ])
            ->assertSessionHasErrors(['agent_ids.1']);

        $this->assertDatabaseCount('kpi_chat_evaluations', 0);
    }

    public function test_download_pdf_generates_valid_pdf_with_expected_filename_content_and_permissions(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Afnan Akbar']);
        $areeba = User::factory()->create(['role' => 'agent', 'name' => 'Areeba']);
        $zoya = User::factory()->create(['role' => 'agent', 'name' => 'Zoya']);
        $laden = User::factory()->create(['role' => 'manager', 'name' => 'Laden']);

        // Non-admin WITH kpis view access (same permission level as View Report)
        $kpiViewer = User::factory()->create([
            'role' => 'agent',
            'name' => 'KPI Viewer',
            'permissions' => ['kpis' => 'view'],
        ]);

        // User with NO KPIs permission
        $noAccessUser = User::factory()->create([
            'role' => 'staff',
            'permissions' => ['kpis' => 'none'],
        ]);

        // Create a multi-agent evaluation (Laden + Zoya) with fails, remarks, and notes
        // Fails: Q1(15), Q5(25), Q6(15), Q7(15) -> lost 70 -> 100/170 (58.82% - Fail)
        $this->actingAs($admin)->post(route('kpi.chat-eval.store'), [
            'agent_ids' => [$laden->id, $zoya->id],
            'evaluation_date' => '2026-11-15',
            'answers' => $this->submittedAnswers(
                [1, 5, 6, 7],
                [
                    1 => 'Missed direct closing opportunity.',
                    5 => 'Response felt rushed and impersonal.',
                ]
            ),
            'notes' => 'Coach on empathetic discovery before quoting prices.',
        ])->assertRedirect();

        $report = KpiChatEvaluation::with(['agent', 'agents', 'evaluator'])->latest('id')->first();
        $this->assertSame(100, $report->total_score);
        $this->assertEquals(58.82, (float) $report->percentage);

        // 1. Render the PDF Blade template directly and verify all 15 questions, remarks, notes, agents & totals
        $pdfHtml = view('kpi.chat-eval.pdf', ['report' => $report])->render();
        $this->assertStringContainsString('Laleen Ops &mdash; Chat Evaluation Report', $pdfHtml);
        $this->assertStringContainsString('15 Nov 2026', $pdfHtml);
        $this->assertStringContainsString('Laden + Zoya', $pdfHtml);
        $this->assertStringContainsString('Afnan Akbar', $pdfHtml);
        $this->assertStringContainsString('100/170 (58.82%)', $pdfHtml);
        $this->assertStringContainsString('Missed direct closing opportunity.', $pdfHtml);
        $this->assertStringContainsString('Response felt rushed and impersonal.', $pdfHtml);
        $this->assertStringContainsString('Coach on empathetic discovery before quoting prices.', $pdfHtml);

        foreach (KpiChatEvaluation::QUESTIONS as $question) {
            $this->assertStringContainsString(e($question['text']), $pdfHtml);
        }
        foreach (array_keys(KpiChatEvaluation::BREAKDOWN_GROUPS) as $category) {
            $this->assertStringContainsString(e($category), $pdfHtml);
        }

        // 2. Hit the real HTTP PDF download endpoint as admin
        $response = $this->actingAs($admin)->get(route('kpi.chat-eval.download', $report));
        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString(
            'chat-evaluation-2026-11-15-laden-zoya.pdf',
            (string) $response->headers->get('Content-Disposition')
        );
        $pdfBytes = $response->getContent();
        $this->assertStringStartsWith('%PDF-', $pdfBytes);
        $this->assertGreaterThan(2000, strlen($pdfBytes));

        // 3. Non-admin WITH kpis view access CAN view and download PDF (same middleware as View Report)
        $this->actingAs($kpiViewer)
            ->get(route('kpi.chat-eval.show', $report))
            ->assertOk();
        $this->actingAs($kpiViewer)
            ->get(route('kpi.chat-eval.download', $report))
            ->assertOk();

        // 4. User WITHOUT kpis permission gets 403 Forbidden
        $this->actingAs($noAccessUser)
            ->get(route('kpi.chat-eval.download', $report))
            ->assertForbidden();
    }
}
