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
}
