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
}
