<?php

namespace Tests\Unit\NovaAI;

use App\NovaAI\Models\NovaBusinessFact;
use App\NovaAI\Services\NovaBusinessFactService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MigratesNovaMemory;
use Tests\TestCase;

/**
 * Stage 5: unit coverage for storage/retrieval/dedup of structured business
 * facts, independent of Gemini/extraction.
 */
class NovaBusinessFactServiceTest extends TestCase
{
    use RefreshDatabase;
    use MigratesNovaMemory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateNovaMemory();
    }

    private function service(): NovaBusinessFactService
    {
        return new NovaBusinessFactService();
    }

    private function fact(string $key, string $value, string $category = 'policy'): array
    {
        return ['category' => $category, 'normalized_key' => $key, 'value' => $value];
    }

    public function test_recording_a_fact_persists_it_as_active(): void
    {
        $this->service()->recordFacts([$this->fact('closing_day', 'Closed on Fridays.')], 1, 'We close on Fridays now.');

        $row = NovaBusinessFact::first();
        $this->assertSame('active', $row->status);
        $this->assertSame('closing_day', $row->normalized_key);
        $this->assertSame('Closed on Fridays.', $row->value);
        $this->assertSame(1, $row->stated_by_user_id);
        $this->assertSame('We close on Fridays now.', $row->source_message);
    }

    public function test_a_new_fact_with_the_same_key_supersedes_the_old_one(): void
    {
        $this->service()->recordFacts([$this->fact('closing_day', 'Closed on Fridays.')], 1, 'msg1');
        $this->service()->recordFacts([$this->fact('closing_day', 'Closed on Sundays instead.')], 1, 'msg2');

        $this->assertSame(2, NovaBusinessFact::count());

        $active = NovaBusinessFact::where('status', 'active')->get();
        $this->assertCount(1, $active);
        $this->assertSame('Closed on Sundays instead.', $active->first()->value);

        $superseded = NovaBusinessFact::where('status', 'superseded')->first();
        $this->assertSame('Closed on Fridays.', $superseded->value);
        $this->assertSame($active->first()->id, $superseded->superseded_by_id);
    }

    public function test_facts_with_different_keys_coexist(): void
    {
        $this->service()->recordFacts([
            $this->fact('closing_day', 'Closed on Fridays.'),
            $this->fact('upsell_target', '2500 QAR per stylist.', 'target'),
        ], 1, 'msg');

        $this->assertSame(2, NovaBusinessFact::where('status', 'active')->count());
    }

    public function test_relevant_facts_returns_category_and_value_only(): void
    {
        $this->service()->recordFacts([$this->fact('closing_day', 'Closed on Fridays.')], 1, 'msg');

        $relevant = $this->service()->relevantFacts('What days are we closed?', 1);

        $this->assertSame([['category' => 'policy', 'value' => 'Closed on Fridays.']], $relevant);
    }

    public function test_relevant_facts_excludes_superseded_ones(): void
    {
        $this->service()->recordFacts([$this->fact('closing_day', 'Closed on Fridays.')], 1, 'msg1');
        $this->service()->recordFacts([$this->fact('closing_day', 'Closed on Sundays instead.')], 1, 'msg2');

        $relevant = $this->service()->relevantFacts('What days are we closed?', 1);

        $this->assertCount(1, $relevant);
        $this->assertSame('Closed on Sundays instead.', $relevant[0]['value']);
    }

    public function test_relevant_facts_is_empty_when_nothing_recorded(): void
    {
        $this->assertSame([], $this->service()->relevantFacts('What days are we closed?', 1));
    }

    public function test_relevant_facts_are_capped_at_twelve_and_ties_broken_by_recency(): void
    {
        // All "policy" category -> every fact shares the same global-category
        // baseline score regardless of keyword overlap with the (deliberately
        // unrelated) question, so this specifically exercises the hard cap +
        // recency tie-break rather than keyword relevance.
        for ($i = 1; $i <= 20; $i++) {
            $this->service()->recordFacts([$this->fact("fact_{$i}", "Value {$i}.")], 1, "msg{$i}");
        }

        $relevant = $this->service()->relevantFacts('Completely unrelated question about nothing here.', 1);

        $this->assertCount(12, $relevant);
        // Most recently recorded (fact_20) must be first - a tie-break, not
        // a keyword match, since the question shares no words with any fact.
        $this->assertSame('Value 20.', $relevant[0]['value']);
    }

    public function test_relevant_facts_excludes_zero_relevance_non_global_category_facts(): void
    {
        $this->service()->recordFacts([$this->fact('payroll_cycle', 'Payroll runs on the 1st of each month.', 'staffing')], 1, 'msg');

        $relevant = $this->service()->relevantFacts('Can I give a client a discount on a combo package?', 1);

        $this->assertSame([], $relevant);
    }
}
