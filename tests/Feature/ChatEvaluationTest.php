<?php

namespace Tests\Feature;

use App\Models\KpiChatEvaluation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatEvaluationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, string> All 15 answers, with the given question numbers forced to 'fail' and the rest 'pass'. */
    private function answersWithFails(array $failNumbers = []): array
    {
        $answers = [];

        foreach (array_keys(KpiChatEvaluation::QUESTIONS) as $num) {
            $answers[$num] = in_array($num, $failNumbers, true) ? 'fail' : 'pass';
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

    public function test_index_and_create_pages_render(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->get(route('kpi.chat-eval.index'))
            ->assertOk()
            ->assertSee('out of 170 points', false);

        $response = $this->actingAs($user)->get(route('kpi.chat-eval.create'));
        $response->assertOk();

        foreach (KpiChatEvaluation::QUESTIONS as $question) {
            $response->assertSee($question['text'], false);
        }
    }

    public function test_store_computes_weighted_total_score_and_percentage_server_side(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $agent = User::factory()->create(['role' => 'agent']);

        // Fails: Q4 (15), Q9 (10), Q13 (5), Q15 (5) -> lost 35 -> 170-35=135 -> 79.41%
        $payload = [
            'agent_id' => $agent->id,
            'evaluation_date' => '2026-10-05',
            'answers' => $this->answersWithFails([4, 9, 13, 15]),
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
    }

    public function test_store_ignores_any_client_submitted_score_and_always_computes_it_server_side(): void
    {
        // Scoring is computed from the weighted question table, not trusted
        // from the request — there is no score field in the payload at all,
        // only pass/fail answers, so this is really a smoke test that a
        // forged non-standard payload shape can't inject an arbitrary score.
        $user = User::factory()->create(['role' => 'admin']);
        $agent = User::factory()->create(['role' => 'agent']);

        $payload = [
            'agent_id' => $agent->id,
            'evaluation_date' => '2026-10-05',
            'answers' => $this->answersWithFails(),
            'total_score' => 99999,
            'percentage' => 9999,
        ];

        $this->actingAs($user)->post(route('kpi.chat-eval.store'), $payload);

        $report = KpiChatEvaluation::first();

        $this->assertSame(170, $report->total_score);
        $this->assertEquals(100.0, (float) $report->percentage);
    }

    public function test_store_rejects_non_binary_answers_and_invalid_agent(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $notAnAgent = User::factory()->create(['role' => 'user']);

        $answers = $this->answersWithFails();
        $answers[1] = 'yes';

        $payload = [
            'agent_id' => $notAnAgent->id,
            'evaluation_date' => '2026-10-05',
            'answers' => $answers,
        ];

        $this->actingAs($user)
            ->post(route('kpi.chat-eval.store'), $payload)
            ->assertSessionHasErrors(['agent_id', 'answers.1']);

        $this->assertDatabaseCount('kpi_chat_evaluations', 0);
    }

    public function test_show_page_renders_question_table_and_score_breakdown(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $agent = User::factory()->create(['role' => 'agent', 'name' => 'Test Agent']);

        $answers = $this->answersWithFails([4]);
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
        $response->assertSee('Edit Report');
    }

    public function test_edit_page_prefills_agent_date_and_checks_saved_answers(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $agent = User::factory()->create(['role' => 'agent', 'name' => 'Edit Agent']);

        $answers = $this->answersWithFails([2]);
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

        $html = $response->getContent();

        $this->assertMatchesRegularExpression('/name="answers\[1\]" value="pass"[^>]*checked/', $html);
        $this->assertMatchesRegularExpression('/name="answers\[2\]" value="fail"[^>]*checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/name="answers\[2\]" value="pass"[^>]*checked/', $html);
    }

    public function test_update_recalculates_score_and_preserves_original_evaluator(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $otherUser = User::factory()->create(['role' => 'admin']);
        $agent = User::factory()->create(['role' => 'agent']);

        $report = KpiChatEvaluation::create([
            'agent_id' => $agent->id,
            'evaluator_id' => $user->id,
            'evaluation_date' => '2026-10-05',
            'answers' => $this->answersWithFails([4, 9, 13, 15]),
            'total_score' => 135,
            'percentage' => 79.41,
        ]);

        $payload = [
            'agent_id' => $agent->id,
            'evaluation_date' => '2026-10-06',
            'answers' => $this->answersWithFails(),
        ];

        $this->actingAs($otherUser)
            ->put(route('kpi.chat-eval.update', $report), $payload)
            ->assertRedirect(route('kpi.chat-eval.show', $report));

        $report->refresh();

        $this->assertSame(170, $report->total_score);
        $this->assertEquals(100.0, (float) $report->percentage);
        $this->assertSame('2026-10-06', $report->evaluation_date->toDateString());
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
            'answers' => $this->answersWithFails(),
            'total_score' => 170,
            'percentage' => 100,
        ]);

        $this->actingAs($user)
            ->delete(route('kpi.chat-eval.destroy', $report))
            ->assertRedirect(route('kpi.chat-eval.index'));

        $this->assertDatabaseCount('kpi_chat_evaluations', 0);
    }
}
