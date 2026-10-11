<?php

namespace Tests\Feature;

use App\Models\BranchEvaluation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchEvaluationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> All 27 answers, with the given fields forced to '1' and the rest '5'. */
    private function answersWithFails(array $failFields = []): array
    {
        $answers = [];

        foreach (BranchEvaluation::questionFields() as $field) {
            $answers[$field] = in_array($field, $failFields, true) ? '1' : '5';
        }

        return $answers;
    }

    public function test_index_page_renders_with_new_evaluation_button_and_branch_filter(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->get(route('kpi.branch-evaluation.index'))
            ->assertOk()
            ->assertSee('Branch Evaluation')
            ->assertSee('New Branch Evaluation')
            ->assertSee('Filter by Branch')
            ->assertSee('All Branches');
    }

    public function test_create_page_renders_all_27_questions_across_7_sections(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->get(route('kpi.branch-evaluation.create'));

        $response->assertOk();

        foreach (BranchEvaluation::SECTIONS as $title => $questions) {
            $response->assertSee($title);

            foreach ($questions as $text) {
                $response->assertSee($text, false);
            }
        }

        $this->assertCount(27, BranchEvaluation::questionFields());
    }

    public function test_store_computes_total_score_percentage_and_grade_correctly(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        // 8 fails -> 135 - (8 * 4) = 103 -> 103/135 = 76.30% -> Pass
        $failFields = ['q4', 'q5', 'q8', 'q10', 'q11', 'q19', 'q20', 'q22'];

        $payload = [
            'branch' => 'old_airport',
            'manager' => 'Ahana',
            'evaluation_date' => '2026-06-16',
            'answers' => $this->answersWithFails($failFields),
            'action_plan' => 'Hygiene check every morning.',
        ];

        $response = $this->actingAs($user)->post(route('kpi.branch-evaluation.store'), $payload);

        $this->assertDatabaseCount('branch_evaluations', 1);

        $evaluation = BranchEvaluation::first();

        $response->assertRedirect(route('kpi.branch-evaluation.show', $evaluation));

        $this->assertSame('old_airport', $evaluation->branch);
        $this->assertSame('Ahana', $evaluation->manager);
        $this->assertSame(103, $evaluation->total_score);
        $this->assertEquals(76.30, (float) $evaluation->percentage);
        $this->assertSame('Pass', $evaluation->grade);
    }

    public function test_grade_boundaries_match_spec(): void
    {
        $this->assertSame('Warning', BranchEvaluation::gradeFor(74.9));
        $this->assertSame('Pass', BranchEvaluation::gradeFor(75));
        $this->assertSame('Pass', BranchEvaluation::gradeFor(89.9));
        $this->assertSame('Excellent', BranchEvaluation::gradeFor(90));
    }

    public function test_store_rejects_non_binary_scores(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $answers = $this->answersWithFails();
        $answers['q1'] = '3';

        $payload = [
            'branch' => 'wakrah',
            'manager' => 'Rhea',
            'evaluation_date' => '2026-06-16',
            'answers' => $answers,
        ];

        $this->actingAs($user)
            ->post(route('kpi.branch-evaluation.store'), $payload)
            ->assertSessionHasErrors('answers.q1');

        $this->assertDatabaseCount('branch_evaluations', 0);
    }

    public function test_store_rejects_incomplete_answers_and_invalid_branch(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $payload = [
            'branch' => 'dubai',
            'manager' => 'Rhea',
            'evaluation_date' => '2026-06-16',
            'answers' => ['q1' => '5'],
        ];

        $this->actingAs($user)
            ->post(route('kpi.branch-evaluation.store'), $payload)
            ->assertSessionHasErrors(['branch', 'answers']);

        $this->assertDatabaseCount('branch_evaluations', 0);
    }

    public function test_show_page_renders_dynamic_section_breakdown_and_critical_perfect_sections(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        // Dress Code & Hygiene: 2 of 3 questions fail -> 7/15 = 46.7% (critical/red).
        // Every other section is a perfect 100%.
        $failFields = ['q4', 'q5'];

        $evaluation = BranchEvaluation::create([
            'branch' => 'wakrah',
            'manager' => 'Rhea',
            'evaluation_date' => '2026-06-16',
            'answers' => $this->answersWithFails($failFields),
            'total_score' => 127,
            'percentage' => round(127 / 135 * 100, 2),
            'grade' => BranchEvaluation::gradeFor(round(127 / 135 * 100, 2)),
            'action_plan' => 'Tighten morning grooming checks.',
        ]);

        $response = $this->actingAs($user)->get(route('kpi.branch-evaluation.show', $evaluation));

        $response->assertOk();
        $response->assertSee('Branch Evaluation Report — Wakrah', false);
        $response->assertSee('Rhea');
        $response->assertSee('Visit 1 of 1');
        $response->assertSee('Dress Code &amp; Hygiene', false);
        $response->assertSee('Tighten morning grooming checks.');

        $critical = $evaluation->sectionResults()->where('tier', 'red')->values();
        $perfect = $evaluation->sectionResults()->where('perfect', true)->values();

        $this->assertCount(1, $critical);
        $this->assertSame('Dress Code & Hygiene', $critical->first()['title']);
        $this->assertCount(6, $perfect);
    }

    public function test_visit_position_counts_prior_evaluations_for_same_branch(): void
    {
        $answers = $this->answersWithFails();

        $first = BranchEvaluation::create([
            'branch' => 'wakrah', 'manager' => 'Rhea', 'evaluation_date' => '2026-05-01',
            'answers' => $answers, 'total_score' => 135, 'percentage' => 100, 'grade' => 'Excellent',
        ]);

        $second = BranchEvaluation::create([
            'branch' => 'wakrah', 'manager' => 'Rhea', 'evaluation_date' => '2026-06-01',
            'answers' => $answers, 'total_score' => 135, 'percentage' => 100, 'grade' => 'Excellent',
        ]);

        // Different branch — must not count toward Wakrah's visit tally.
        BranchEvaluation::create([
            'branch' => 'old_airport', 'manager' => 'Ahana', 'evaluation_date' => '2026-05-15',
            'answers' => $answers, 'total_score' => 135, 'percentage' => 100, 'grade' => 'Excellent',
        ]);

        $this->assertSame(['number' => 1, 'total' => 2], $first->visitPosition());
        $this->assertSame(['number' => 2, 'total' => 2], $second->visitPosition());
    }

    public function test_branch_filter_restricts_index_table(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $answers = $this->answersWithFails();

        BranchEvaluation::create([
            'branch' => 'wakrah', 'manager' => 'Rhea', 'evaluation_date' => '2026-06-16',
            'answers' => $answers, 'total_score' => 135, 'percentage' => 100, 'grade' => 'Excellent',
        ]);

        BranchEvaluation::create([
            'branch' => 'old_airport', 'manager' => 'Ahana', 'evaluation_date' => '2026-06-16',
            'answers' => $answers, 'total_score' => 135, 'percentage' => 100, 'grade' => 'Excellent',
        ]);

        $this->actingAs($user)
            ->get(route('kpi.branch-evaluation.index', ['branch' => 'wakrah']))
            ->assertOk()
            ->assertSee('Rhea')
            ->assertDontSee('Ahana');
    }

    public function test_create_and_edit_pages_use_a_readonly_manager_input_driven_by_branch_select(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $createResponse = $this->actingAs($user)->get(route('kpi.branch-evaluation.create'));
        $createResponse->assertOk();
        $createResponse->assertSee('id="branch_select"', false);
        $createResponse->assertSee('id="manager_input"', false);
        $createResponse->assertSee('readonly', false);

        $evaluation = BranchEvaluation::create([
            'branch' => 'wakrah', 'manager' => 'Rhea', 'evaluation_date' => '2026-06-16',
            'answers' => $this->answersWithFails(), 'total_score' => 135, 'percentage' => 100, 'grade' => 'Excellent',
        ]);

        $editResponse = $this->actingAs($user)->get(route('kpi.branch-evaluation.edit', $evaluation));
        $editResponse->assertOk();
        $editResponse->assertSee('id="branch_select"', false);
        $editResponse->assertSee('id="manager_input"', false);
        $editResponse->assertSee('Edit Branch Evaluation');
    }

    public function test_edit_page_prefills_branch_manager_date_action_plan_and_checks_saved_answers(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $failFields = ['q4', 'q5'];
        $answers = $this->answersWithFails($failFields);

        $evaluation = BranchEvaluation::create([
            'branch' => 'wakrah',
            'manager' => 'Rhea',
            'evaluation_date' => '2026-06-16',
            'answers' => $answers,
            'total_score' => 127,
            'percentage' => round(127 / 135 * 100, 2),
            'grade' => BranchEvaluation::gradeFor(round(127 / 135 * 100, 2)),
            'action_plan' => 'Existing coaching notes.',
        ]);

        $response = $this->actingAs($user)->get(route('kpi.branch-evaluation.edit', $evaluation));

        $response->assertOk();
        $response->assertSee('Existing coaching notes.');

        $html = $response->getContent();

        // q1 is a pass (5) in the fixture — its "5" radio must be checked, its "1" radio must not be.
        $this->assertMatchesRegularExpression('/name="answers\[q1\]" value="5"[^>]*checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/name="answers\[q1\]" value="1"[^>]*checked/', $html);

        // q4 is a fail (1) in the fixture — its "1" radio must be checked, its "5" radio must not be.
        $this->assertMatchesRegularExpression('/name="answers\[q4\]" value="1"[^>]*checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/name="answers\[q4\]" value="5"[^>]*checked/', $html);
    }

    public function test_update_recalculates_total_score_percentage_and_grade(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $evaluation = BranchEvaluation::create([
            'branch' => 'old_airport', 'manager' => 'Ahana', 'evaluation_date' => '2026-06-16',
            'answers' => $this->answersWithFails(['q4', 'q5', 'q8', 'q10', 'q11', 'q19', 'q20', 'q22']),
            'total_score' => 103, 'percentage' => 76.30, 'grade' => 'Pass',
        ]);

        // Flip to a perfect score on update.
        $payload = [
            'branch' => 'old_airport',
            'manager' => 'Ahana',
            'evaluation_date' => '2026-07-01',
            'answers' => $this->answersWithFails(),
            'action_plan' => 'Updated after re-visit.',
        ];

        $response = $this->actingAs($user)->put(route('kpi.branch-evaluation.update', $evaluation), $payload);

        $response->assertRedirect(route('kpi.branch-evaluation.show', $evaluation));

        $evaluation->refresh();

        $this->assertSame(135, $evaluation->total_score);
        $this->assertEquals(100.0, (float) $evaluation->percentage);
        $this->assertSame('Excellent', $evaluation->grade);
        $this->assertSame('Updated after re-visit.', $evaluation->action_plan);
        $this->assertSame('2026-07-01', $evaluation->evaluation_date->toDateString());
        $this->assertDatabaseCount('branch_evaluations', 1);
    }

    public function test_update_rejects_non_binary_scores_and_leaves_record_unchanged(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $evaluation = BranchEvaluation::create([
            'branch' => 'wakrah', 'manager' => 'Rhea', 'evaluation_date' => '2026-06-16',
            'answers' => $this->answersWithFails(), 'total_score' => 135, 'percentage' => 100, 'grade' => 'Excellent',
        ]);

        $answers = $this->answersWithFails();
        $answers['q1'] = '3';

        $payload = [
            'branch' => 'wakrah',
            'manager' => 'Rhea',
            'evaluation_date' => '2026-06-16',
            'answers' => $answers,
        ];

        $this->actingAs($user)
            ->put(route('kpi.branch-evaluation.update', $evaluation), $payload)
            ->assertSessionHasErrors('answers.q1');

        $evaluation->refresh();
        $this->assertSame(135, $evaluation->total_score);
        $this->assertSame('Excellent', $evaluation->grade);
    }

    public function test_index_and_show_pages_expose_edit_entry_points(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $evaluation = BranchEvaluation::create([
            'branch' => 'wakrah', 'manager' => 'Rhea', 'evaluation_date' => '2026-06-16',
            'answers' => $this->answersWithFails(), 'total_score' => 135, 'percentage' => 100, 'grade' => 'Excellent',
        ]);

        $this->actingAs($user)
            ->get(route('kpi.branch-evaluation.index'))
            ->assertOk()
            ->assertSee(route('kpi.branch-evaluation.edit', $evaluation), false);

        $this->actingAs($user)
            ->get(route('kpi.branch-evaluation.show', $evaluation))
            ->assertOk()
            ->assertSee('Edit Report')
            ->assertSee(route('kpi.branch-evaluation.edit', $evaluation), false);
    }
}
