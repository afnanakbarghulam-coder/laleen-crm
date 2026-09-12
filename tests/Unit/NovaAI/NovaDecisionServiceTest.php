<?php

namespace Tests\Unit\NovaAI;

use App\NovaAI\Models\NovaDecision;
use App\NovaAI\Models\NovaExperiment;
use App\NovaAI\Services\NovaDecisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MigratesNovaMemory;
use Tests\TestCase;

/**
 * Stage 6: unit coverage for storage/retrieval/lifecycle transitions of
 * decisions and experiments, independent of Gemini/extraction.
 */
class NovaDecisionServiceTest extends TestCase
{
    use RefreshDatabase;
    use MigratesNovaMemory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateNovaMemory();
    }

    private function service(): NovaDecisionService
    {
        return new NovaDecisionService();
    }

    private function createDecisionAction(string $title = 'Prioritize Wakrah hiring', string $category = 'staffing'): array
    {
        return ['action' => 'create', 'type' => 'decision', 'title' => $title, 'category' => $category, 'description' => null, 'review_date' => null];
    }

    private function createExperimentAction(string $title = 'Tuesday discount trial'): array
    {
        return [
            'action' => 'create',
            'type' => 'experiment',
            'title' => $title,
            'category' => 'pricing',
            'description' => null,
            'review_date' => null,
            'started_at' => '2026-09-15',
            'ends_at' => '2026-09-29',
            'target_metric' => 'Tuesday bookings',
            'success_criteria' => null,
        ];
    }

    public function test_create_action_persists_a_decision(): void
    {
        $this->service()->applyActions([$this->createDecisionAction()], 1, 'We decided to prioritize Wakrah hiring.');

        $this->assertSame(1, NovaDecision::count());
        $decision = NovaDecision::first();
        $this->assertSame('Prioritize Wakrah hiring', $decision->title);
        $this->assertSame('staffing', $decision->category);
        $this->assertSame('active', $decision->status);
        $this->assertSame(1, $decision->decided_by_user_id);
    }

    public function test_create_action_persists_an_experiment_with_its_own_fields(): void
    {
        $this->service()->applyActions([$this->createExperimentAction()], 1, 'Let\'s try a Tuesday discount.');

        $this->assertSame(1, NovaExperiment::count());
        $experiment = NovaExperiment::first();
        $this->assertSame('Tuesday discount trial', $experiment->title);
        $this->assertSame('2026-09-15', $experiment->started_at->toDateString());
        $this->assertSame('2026-09-29', $experiment->ends_at->toDateString());
        $this->assertSame('Tuesday bookings', $experiment->target_metric);
    }

    public function test_complete_action_transitions_a_decision_and_stores_result_summary(): void
    {
        $this->service()->applyActions([$this->createDecisionAction()], 1, 'msg');
        $id = NovaDecision::first()->id;

        $this->service()->applyActions([
            ['action' => 'complete', 'target_id' => $id, 'result_summary' => 'Done early.'],
        ], 1, 'It\'s done.');

        $decision = NovaDecision::find($id);
        $this->assertSame('completed', $decision->status);
        $this->assertSame('Done early.', $decision->result_summary);
    }

    public function test_cancel_action_transitions_an_experiment(): void
    {
        $this->service()->applyActions([$this->createExperimentAction()], 1, 'msg');
        $id = NovaExperiment::first()->id;

        $this->service()->applyActions([
            ['action' => 'cancel', 'target_id' => $id, 'result_summary' => null],
        ], 1, 'Cancel that trial.');

        $this->assertSame('cancelled', NovaExperiment::find($id)->status);
    }

    public function test_reverse_action_transitions_a_decision(): void
    {
        $this->service()->applyActions([$this->createDecisionAction()], 1, 'msg');
        $id = NovaDecision::first()->id;

        $this->service()->applyActions([
            ['action' => 'reverse', 'target_id' => $id, 'result_summary' => null],
        ], 1, 'Actually reverse that.');

        $this->assertSame('reversed', NovaDecision::find($id)->status);
    }

    public function test_transition_on_an_already_inactive_item_is_a_no_op(): void
    {
        $this->service()->applyActions([$this->createDecisionAction()], 1, 'msg');
        $id = NovaDecision::first()->id;

        $this->service()->applyActions([['action' => 'complete', 'target_id' => $id, 'result_summary' => null]], 1, 'msg2');
        $this->service()->applyActions([['action' => 'cancel', 'target_id' => $id, 'result_summary' => null]], 1, 'msg3');

        // Already completed - the later cancel must not overwrite it.
        $this->assertSame('completed', NovaDecision::find($id)->status);
    }

    public function test_active_items_for_extraction_lists_both_types(): void
    {
        $this->service()->applyActions([$this->createDecisionAction()], 1, 'msg');
        $this->service()->applyActions([$this->createExperimentAction()], 1, 'msg');

        $items = $this->service()->activeItemsForExtraction();

        $this->assertCount(2, $items);
        $this->assertEqualsCanonicalizing(['decision', 'experiment'], array_column($items, 'type'));
    }

    public function test_active_items_for_extraction_excludes_inactive_ones(): void
    {
        $this->service()->applyActions([$this->createDecisionAction()], 1, 'msg');
        $id = NovaDecision::first()->id;
        $this->service()->applyActions([['action' => 'complete', 'target_id' => $id, 'result_summary' => null]], 1, 'msg2');

        $this->assertSame([], $this->service()->activeItemsForExtraction());
    }

    public function test_relevant_decisions_returns_display_shape(): void
    {
        $this->service()->applyActions([$this->createDecisionAction()], 1, 'msg');

        // Decisions carry an unconditional relevance baseline (see class
        // docblock) so they surface even for a fully unrelated question.
        $decisions = $this->service()->relevantDecisions('Completely unrelated question.');

        $this->assertCount(1, $decisions);
        $this->assertSame('Prioritize Wakrah hiring', $decisions[0]['title']);
        $this->assertSame('staffing', $decisions[0]['category']);
    }

    public function test_relevant_experiments_returns_display_shape_with_dates_when_topically_relevant(): void
    {
        $this->service()->applyActions([$this->createExperimentAction()], 1, 'msg');

        $experiments = $this->service()->relevantExperiments('What is happening with the Tuesday discount trial?');

        $this->assertCount(1, $experiments);
        $this->assertSame('2026-09-15', $experiments[0]['started_at']);
        $this->assertSame('2026-09-29', $experiments[0]['ends_at']);
    }

    public function test_relevant_experiments_excludes_a_topically_unrelated_experiment(): void
    {
        // Experiments carry no unconditional baseline (unlike decisions) -
        // relevance is driven entirely by topical overlap with the question.
        $this->service()->applyActions([$this->createExperimentAction()], 1, 'msg');

        $experiments = $this->service()->relevantExperiments('How is staff payroll looking this month?');

        $this->assertSame([], $experiments);
    }

    public function test_relevant_decisions_gives_an_overdue_review_a_boost(): void
    {
        $this->service()->applyActions([
            ['action' => 'create', 'type' => 'decision', 'title' => 'Old Airport hiring freeze', 'category' => 'staffing', 'description' => null, 'review_date' => '2020-01-01'],
        ], 1, 'msg');
        $this->service()->applyActions([
            ['action' => 'create', 'type' => 'decision', 'title' => 'Wakrah marketing push', 'category' => 'marketing', 'description' => null, 'review_date' => null],
        ], 1, 'msg');

        $decisions = $this->service()->relevantDecisions('Completely unrelated question.');

        // Both share the same base score (no keyword overlap with either) -
        // the overdue review_date must be what pushes the hiring freeze first.
        $this->assertSame('Old Airport hiring freeze', $decisions[0]['title']);
    }

    public function test_completed_items_are_excluded_from_relevant_lists(): void
    {
        $this->service()->applyActions([$this->createDecisionAction()], 1, 'msg');
        $id = NovaDecision::first()->id;
        $this->service()->applyActions([['action' => 'complete', 'target_id' => $id, 'result_summary' => null]], 1, 'msg2');

        $this->assertSame([], $this->service()->relevantDecisions('Completely unrelated question.'));
    }

    public function test_relevant_decisions_are_capped_at_twelve(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            $this->service()->applyActions([
                ['action' => 'create', 'type' => 'decision', 'title' => "Decision {$i}", 'category' => 'other', 'description' => null, 'review_date' => null],
            ], 1, 'msg');
        }

        $decisions = $this->service()->relevantDecisions('Completely unrelated question.');

        $this->assertCount(12, $decisions);
    }
}
